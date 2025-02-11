<?php

namespace App\Http\Requests\Envato;

use Illuminate\Foundation\Http\FormRequest;

class ConvertPurchaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'purchase_code' => 'required|string|min:23|max:36',
            'buyer_email' => 'nullable|email'
        ];
    }
}