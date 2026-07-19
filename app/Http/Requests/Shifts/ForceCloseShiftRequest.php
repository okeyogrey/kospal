<?php

namespace App\Http\Requests\Shifts;

use App\Models\StaffShift;
use Illuminate\Foundation\Http\FormRequest;

class ForceCloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StaffShift $shift */
        $shift = $this->route('shift');

        return $this->user()?->can('forceClose', $shift) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'close_reason' => ['required', 'string', 'max:255'],
        ];
    }
}
