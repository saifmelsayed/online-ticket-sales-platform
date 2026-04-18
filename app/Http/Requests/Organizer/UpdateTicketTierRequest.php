<?php

namespace App\Http\Requests\Organizer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTicketTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'base_price' => ['sometimes', 'numeric', 'min:0'],
            'total_seats' => ['sometimes', 'integer', 'min:1'],
            'sale_starts_at' => ['sometimes', 'date'],
            'sale_ends_at' => ['sometimes', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $keys = ['name', 'base_price', 'total_seats', 'sale_starts_at', 'sale_ends_at'];
            $hasAny = false;
            foreach ($keys as $key) {
                if ($this->has($key)) {
                    $hasAny = true;
                    break;
                }
            }
            if (!$hasAny) {
                $validator->errors()->add('tier', 'Provide at least one field to update.');
            }
        });
    }
}
