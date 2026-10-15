<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AssignTicketRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = User::find($value);
                    if (! $user || ! $user->isAgent() || ! $user->isAvailable()) {
                        $fail('The selected agent is not available for assignment.');
                    }
                }
            ]
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}