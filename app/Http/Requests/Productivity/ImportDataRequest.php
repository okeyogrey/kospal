<?php

namespace App\Http\Requests\Productivity;

use App\Enums\ImportEntity;
use App\Enums\SpreadsheetFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entity = ImportEntity::tryFrom((string) $this->input('entity'));
        $format = SpreadsheetFormat::tryFrom((string) $this->input('format'));

        if ($entity === null || $format === null) {
            return false;
        }

        return $this->user()?->can('import', [
            ImportEntity::from((string) $this->input('entity')),
            SpreadsheetFormat::from((string) $this->input('format')),
        ]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entity' => ['required', Rule::enum(ImportEntity::class)],
            'format' => ['required', Rule::enum(SpreadsheetFormat::class)],
            'file' => ['required', 'file', 'max:5120', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value instanceof \Illuminate\Http\UploadedFile) {
                    return;
                }

                $format = SpreadsheetFormat::tryFrom((string) $this->input('format'));
                if ($format === null) {
                    return;
                }

                $extension = strtolower($value->getClientOriginalExtension());
                $allowed = match ($format) {
                    SpreadsheetFormat::Csv => ['csv', 'txt'],
                    SpreadsheetFormat::Xlsx => ['xlsx'],
                };

                if (! in_array($extension, $allowed, true)) {
                    $fail('The file must be a .'.implode(' or .', $allowed).' file.');
                }
            }],
            'update_existing' => ['sometimes', 'boolean'],
        ];
    }
}
