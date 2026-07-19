<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Expense;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    public function diskName(): string
    {
        return (string) config('kospal.attachments.disk', 'attachments');
    }

    /**
     * Store a private attachment for an expense (or future attachables).
     * Path layout is disk-agnostic so the underlying driver can change later.
     */
    public function storeForExpense(
        Expense $expense,
        UploadedFile $file,
        User $actor,
    ): Attachment {
        $this->assertAllowedUpload($file);

        $disk = $this->diskName();
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $allowed = config('kospal.attachments.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png', 'webp']);
        abort_unless(in_array($extension, $allowed, true), 422, 'Unsupported attachment type.');

        $directory = sprintf(
            '%d/expenses/%d',
            $expense->business_id,
            $expense->id,
        );
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs($directory, $filename, [
            'disk' => $disk,
            'visibility' => 'private',
        ]);

        abort_unless(is_string($path) && $path !== '', 500, 'Failed to store attachment.');

        $attachment = Attachment::query()->create([
            'business_id' => $expense->business_id,
            'attachable_type' => $expense->getMorphClass(),
            'attachable_id' => $expense->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
            'uploaded_by' => $actor->id,
        ]);

        $this->audit->log(
            action: 'expense.attachment_added',
            auditable: $expense,
            metadata: [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
            ],
            actor: $actor,
            businessId: $expense->business_id,
        );

        return $attachment;
    }

    public function replaceExpenseReceipt(
        Expense $expense,
        UploadedFile $file,
        User $actor,
    ): Attachment {
        $existing = $expense->attachments()->get();

        foreach ($existing as $attachment) {
            $this->delete($attachment, $actor, audit: false);
        }

        return $this->storeForExpense($expense, $file, $actor);
    }

    public function delete(Attachment $attachment, User $actor, bool $audit = true): void
    {
        $businessId = $attachment->business_id;
        $attachable = $attachment->attachable;
        $payload = [
            'attachment_id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'attachable_type' => $attachment->attachable_type,
            'attachable_id' => $attachment->attachable_id,
        ];

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();

        if ($audit && $attachable instanceof Expense) {
            $this->audit->log(
                action: 'expense.attachment_removed',
                auditable: $attachable,
                metadata: $payload,
                actor: $actor,
                businessId: $businessId,
            );
        }
    }

    public function deleteAllFor(Model $attachable, User $actor): void
    {
        if (! method_exists($attachable, 'attachments')) {
            return;
        }

        foreach ($attachable->attachments()->get() as $attachment) {
            $this->delete($attachment, $actor, audit: false);
        }
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        abort_unless(
            Storage::disk($attachment->disk)->exists($attachment->path),
            404,
        );

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type,
            ],
        );
    }

    /**
     * @return array{id: int, original_name: string, mime_type: string, size: int, download_url: string}
     */
    public function payload(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'download_url' => route('attachments.download', $attachment),
        ];
    }

    protected function assertAllowedUpload(UploadedFile $file): void
    {
        $maxKb = (int) config('kospal.attachments.max_kilobytes', 5120);
        $size = $file->getSize() ?: 0;
        abort_if($size <= 0 || $size > $maxKb * 1024, 422, 'Attachment exceeds the allowed size.');

        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
        ];
        $detected = $file->getMimeType() ?: $file->getClientMimeType();
        abort_unless(
            is_string($detected) && in_array($detected, $allowedMimes, true),
            422,
            'Attachment mime type is not allowed.',
        );
    }
}
