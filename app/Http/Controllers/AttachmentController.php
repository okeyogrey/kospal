<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\AttachmentService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function download(Attachment $attachment, AttachmentService $attachments): StreamedResponse
    {
        $this->authorize('download', $attachment);

        return $attachments->download($attachment);
    }
}
