<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class SyncCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket_tier_id' => ['required', 'integer', 'exists:ticket_tiers,id'],
            'quantity' => ['required', 'integer', 'min:0', 'max:500'],
        ];
    }
}
