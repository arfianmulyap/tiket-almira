<?php

namespace App\Http\Requests\Admin;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;

class StoreCheckInRequest extends FormRequest
{
    /**
     * Only an authorised operator may scan (PRD FR-10).
     *
     * Checked through the policy rather than relying on the admin middleware
     * alone, so the ability has one definition shared with the gate screen.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('checkIn', Ticket::class) ?? false;
    }

    /**
     * Validation here is deliberately shallow.
     *
     * The token is only checked for presence and a sane length. It is *not*
     * checked for the exact 64-character alphanumeric shape, because a
     * malformed payload must produce the normal "QR tidak valid" result on
     * the gate screen rather than a field validation error: the officer is
     * scanning a code, not filling in a form, and a 422 with a field error
     * would be a dead end on a camera-driven screen.
     *
     * CheckInService decides what the payload actually means. The max length
     * only stops an unbounded string reaching it.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * The scanned payload.
     */
    public function token(): string
    {
        /** @var string $token */
        $token = $this->validated('token');

        return $token;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'token' => 'token tiket',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'Token tiket wajib diisi.',
            'token.max' => 'Token tiket tidak dikenali.',
        ];
    }
}
