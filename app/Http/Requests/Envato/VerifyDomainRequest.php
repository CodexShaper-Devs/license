<?php

namespace App\Http\Requests\Envato;

use Illuminate\Foundation\Http\FormRequest;

class VerifyDomainRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'domain' => 'required|string|max:255',
            'activation_token' => 'required|string'
        ];
    }
}