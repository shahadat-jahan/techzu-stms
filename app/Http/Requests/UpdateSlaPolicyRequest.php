<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSlaPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSlaSettings') === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution_minutes' => ['required', 'integer', 'min:1', 'max:525600'],
            'warning_minutes' => ['required', 'integer', 'min:1', 'max:525600', 'lte:resolution_minutes'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'warning_minutes.lte' => 'The warning window must not exceed the resolution window.',
        ];
    }
}
