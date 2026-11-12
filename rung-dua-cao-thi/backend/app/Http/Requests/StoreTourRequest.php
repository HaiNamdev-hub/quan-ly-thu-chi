<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            'tour_date' => ['required', 'date'],
            'guide_id' => ['nullable', 'integer', Rule::exists('guides', 'id')->where('is_active', true)],
            'vietnamese_guests' => ['required', 'integer', 'min:0', 'max:1000'],
            'international_guests' => ['required', 'integer', 'min:0', 'max:1000'],
            'coconut_portions' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'ao_dai_units' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'ao_dai_revenue' => ['sometimes', 'integer', 'min:0'],
            'other_expenses' => ['sometimes', 'integer', 'min:0'],
            'ticket_payment_method' => ['nullable', Rule::in(['cash', 'company_transfer', 'personal_transfer'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'photos' => ['sometimes', 'array'],
            'photos.large_frame' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'photos.small_frame' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'photos.large_plastic' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'photos.small_plastic' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
