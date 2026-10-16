<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    /**
     * Authorization is enforced in the controller via TicketPolicy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Customers may edit only subject and description; staff may also change
     * priority and category. Keys absent from these rules are filtered out of
     * validated() and therefore never reach the service.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
        ];

        $user = $this->user();

        if ($user !== null && ! $user->isCustomer()) {
            $rules['priority'] = ['sometimes', 'required', Rule::enum(Priority::class)];
            $rules['category_id'] = ['sometimes', 'required', 'exists:categories,id'];
        }

        return $rules;
    }
}
