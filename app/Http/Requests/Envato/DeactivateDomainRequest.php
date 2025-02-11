<?php

namespace App\Http\Requests\Envato;

use Illuminate\Foundation\Http\FormRequest;

class DeactivateDomainRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'domain' => 'required|string|max:255'
        ];
    }
}