<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketReplyRequest extends FormRequest
{
    /**
     * Authorization runs in the controller via TicketPolicy::reply /
     * TicketPolicy::addInternalNote after this request validates the payload.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'is_internal' => [
                'sometimes',
                'boolean',
                Rule::prohibitedIf(fn (): bool => $this->user()?->isCustomer() ?? true),
            ],
        ];
    }
}
