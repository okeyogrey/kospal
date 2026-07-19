<?php

namespace App\Http\Requests\Shifts;

use App\Models\StaffShift;
use Illuminate\Foundation\Http\FormRequest;

class ClockInShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('clockIn', StaffShift::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
