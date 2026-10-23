<?php

namespace App\Http\Requests\Admin;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEventRequest extends FormRequest
{
    /**
     * Authorisation is delegated to EventPolicy::create().
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Event::class) ?? false;
    }

    /**
     * Poster constraints (jpg/jpeg/png/webp, max 4 MB) are an assumption
     * recorded against PRD open question 9 and approved by the business owner.
     *
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
     * A poster is optional while the event is a draft but mandatory before it
     * is published, because the public ordering page shows it at the very top
     * (PRD FR-03).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $publishing = $this->input('status') === EventStatus::Published->value;

            if ($publishing && ! $this->hasFile('poster')) {
                $validator->errors()->add(
                    'poster',
                    'Poster wajib diunggah sebelum event dipublikasikan.'
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
