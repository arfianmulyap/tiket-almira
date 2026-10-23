<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Ordering is open to the public; whether the specific event accepts
     * orders is decided by the controller and re-checked inside
     * OrderService, not here.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only participant data is accepted. Price, status, and deadline are
     * never taken from input (PRD FR-04).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'participant_name' => ['required', 'string', 'min:3', 'max:120'],
            'participant_whatsapp' => [
                'required',
                'string',
                'min:9',
                'max:20',
                // Indonesian mobile numbers, accepting the 08.. and +62..
                // forms people actually type. Normalised in prepareForValidation.
                'regex:/^(0|62)8[1-9][0-9]{6,11}$/',
            ],
            // `rfc` only, deliberately not `dns`: a DNS check would make
            // every order submission depend on a live resolver lookup and
            // would reject valid addresses whenever DNS is slow or blocked.
            'participant_email' => ['required', 'string', 'email:rfc', 'max:160'],
        ];
    }

    /**
     * Strip separators from the WhatsApp number before validating so that
     * "0812-3456-7890" and "+62 812 3456 7890" are both accepted and stored
     * in one consistent shape.
     */
    protected function prepareForValidation(): void
    {
        $whatsapp = $this->input('participant_whatsapp');

        if (is_string($whatsapp)) {
            $this->merge([
                'participant_whatsapp' => preg_replace('/[^0-9]/', '', $whatsapp) ?? '',
            ]);
        }

        $name = $this->input('participant_name');

        if (is_string($name)) {
            $this->merge(['participant_name' => trim($name)]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'participant_name' => 'nama lengkap',
            'participant_whatsapp' => 'nomor WhatsApp',
            'participant_email' => 'email',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'participant_whatsapp.regex' => 'Masukkan nomor WhatsApp Indonesia yang valid, contoh 081234567890.',
            'participant_email.email' => 'Masukkan alamat email aktif yang valid.',
        ];
    }

    /**
     * Participant fields in the shape OrderService expects.
     *
     * @return array{participant_name: string, participant_whatsapp: string, participant_email: string}
     */
    public function participantData(): array
    {
        /** @var array{participant_name: string, participant_whatsapp: string, participant_email: string} $data */
        $data = $this->safe()->only([
            'participant_name',
            'participant_whatsapp',
            'participant_email',
        ]);

        return $data;
    }
}
