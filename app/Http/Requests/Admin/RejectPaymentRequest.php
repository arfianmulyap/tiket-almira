<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RejectPaymentRequest extends FormRequest
{
    /**
     * Authorisation is delegated to OrderPolicy::decide(), which also
     * confirms the order is still awaiting a decision.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('decide', $this->route('order')) ?? false;
    }

    /**
     * The reason is mandatory: PRD FR-07 requires a rejection to be recorded
     * with its reason, and it is what the participant and any later audit
     * will rely on to understand the decision.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rejection_reason' => 'alasan penolakan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Alasan penolakan wajib diisi.',
            'rejection_reason.min' => 'Jelaskan alasan penolakan minimal 10 karakter.',
        ];
    }
}
