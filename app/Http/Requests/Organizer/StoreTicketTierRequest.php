<?php

namespace App\Http\Requests\Organizer;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'total_seats' => ['required', 'integer', 'min:1'],
            'sale_starts_at' => ['required', 'date'],
            'sale_ends_at' => ['required', 'date', 'after:sale_starts_at'],
        ];
    }
}
