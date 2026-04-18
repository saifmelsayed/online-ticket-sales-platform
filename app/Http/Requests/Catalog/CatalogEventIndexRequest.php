<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class CatalogEventIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'is_online' => ['sometimes', 'boolean'],
            'venue' => ['sometimes', 'string', 'max:255'],
            'price_min' => ['sometimes', 'numeric', 'min:0'],
            'price_max' => ['sometimes', 'numeric', 'min:0'],
            'organizer_id' => ['sometimes', 'integer', 'exists:users,id'],
            'has_availability' => ['sometimes', 'boolean'],
            'include_past' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('date_to') && $this->has('date_from')) {
            $from = $this->date('date_from');
            $to = $this->date('date_to');
            if ($from && $to && $to->lt($from)) {
                $this->merge(['date_to' => $from->toDateString()]);
            }
        }
    }
}
