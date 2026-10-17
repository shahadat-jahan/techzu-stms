<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
{
    /**
     * Authorization is enforced via the 'manageImports' gate.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manageImports') === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:'.(config('support.import.max_size') * 1024), // Convert MB to KB
            ],
        ];
    }
}
