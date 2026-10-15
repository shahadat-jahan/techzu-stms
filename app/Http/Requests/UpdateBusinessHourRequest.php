<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBusinessHourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageSlaSettings') === true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['open_time', 'close_time'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => CarbonImmutable::parse($this->input($field))->format('H:i:s')]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_open' => ['required', 'boolean'],
            'open_time' => ['exclude_unless:is_open,true', 'required', 'date_format:H:i,H:i:s'],
            'close_time' => ['exclude_unless:is_open,true', 'required', 'date_format:H:i,H:i:s', 'after:open_time'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'close_time.after' => 'The closing time must be after the opening time.',
        ];
    }
}
