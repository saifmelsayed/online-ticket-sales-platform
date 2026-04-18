<?php

namespace App\Http\Requests\Organizer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'event_datetime' => ['sometimes', 'date'],
            'is_online' => ['sometimes', 'boolean'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'banner_image' => ['nullable', 'image', 'max:2048'],
            'status' => ['sometimes', Rule::in(['upcoming', 'cancelled', 'completed'])],
        ];
    }
}
