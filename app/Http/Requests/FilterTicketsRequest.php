<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\SlaStatus;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, Rule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'number' => ['nullable', 'string', 'max:50'],
            'keyword' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::enum(TicketStatus::class)],
            'priority' => ['nullable', 'string', Rule::enum(Priority::class)],
            'assigned_to' => ['nullable', 'string', 'max:50'],
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'created_from' => ['nullable', 'string'],
            'created_to' => ['nullable', 'string'],
            'sla_status' => ['nullable', 'string', Rule::enum(SlaStatus::class)],
            'sort_by' => ['nullable', 'string', Rule::in(['created_at', 'due_at', 'priority'])],
            'sort_dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }
}
