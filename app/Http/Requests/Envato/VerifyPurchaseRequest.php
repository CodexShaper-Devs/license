<?php

namespace App\Http\Requests\Envato;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPurchaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'purchase_code' => 'required|string',
            'item_id' => 'required|string',
        ];
    }
}