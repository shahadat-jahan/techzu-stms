<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    /**
     * Authorization runs in the controller via TicketPolicy::reply, which
     * governs who may attach files to a ticket conversation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The ticket comes from route-model binding, so only the files need
     * validation here. Size, count, and MIME limits live in config/support.php.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'max:'.config('support.uploads.max_files')],
            'files.*' => [
                'file',
                'max:'.config('support.uploads.max_size'),
                'mimes:'.implode(',', config('support.uploads.allowed_mimes')),
            ],
        ];
    }
}
