<?php

namespace App\Http\Requests\Admin;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEventRequest extends FormRequest
{
    /**
     * Authorisation is delegated to EventPolicy::update().
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->event()) ?? false;
    }

    /**
     * The event being updated, resolved from the route.
     */
    public function event(): Event
    {
        return $this->route('event');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'ticket_price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'quota' => ['required', 'integer', 'min:1', 'max:1000000'],
            'status' => ['required', Rule::enum(EventStatus::class)],
            'starts_at' => ['required', 'date'],
            'location' => ['required', 'string', 'max:200'],
            'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * Two rules that cannot be expressed as plain field rules:
     *
     * 1. Publishing requires a poster, either already stored or newly
     *    uploaded in this request (PRD FR-03).
     * 2. Quota may be raised but never lowered below the number of slots
     *    already taken, otherwise the event would be oversold. This is an
     *    assumption recorded against PRD open question 11 and approved by
     *    the business owner.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $event = $this->event();

            $publishing = $this->input('status') === EventStatus::Published->value;
            $hasPoster = $this->hasFile('poster') || filled($event->poster_path);

            if ($publishing && ! $hasPoster) {
                $validator->errors()->add(
                    'poster',
                    'Poster wajib diunggah sebelum event dipublikasikan.'
                );
            }

            $reserved = $event->reservedQuota();
            $quota = $this->input('quota');

            if (is_numeric($quota) && (int) $quota < $reserved) {
                $validator->errors()->add(
                    'quota',
                    "Kuota tidak boleh kurang dari {$reserved} slot yang sudah terpakai."
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama event',
            'description' => 'deskripsi',
            'ticket_price' => 'harga tiket',
            'quota' => 'kuota',
            'status' => 'status',
            'starts_at' => 'waktu mulai',
            'location' => 'lokasi',
            'poster' => 'poster',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'poster.max' => 'Poster tidak boleh lebih besar dari 4 MB.',
            'poster.mimes' => 'Poster harus berformat JPG, PNG, atau WebP.',
            'quota.min' => 'Kuota minimal 1 tiket.',
        ];
    }
}
