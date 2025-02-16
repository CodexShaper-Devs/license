<?php

namespace App\Http\Requests\Envato;

use App\Http\Requests\ApiFormRequest;

class ActivateEnvatoPurchaseRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'purchase_code' => 'required|string|min:23|max:36',
            'item_id' => 'required|string',
            'email' => 'required|email',
            'domain' => 'required|string|max:255',
            'device_identifier' => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'hardware_info' => 'nullable|array',
            'hardware_info.cpu' => 'nullable|string',
            'hardware_info.ram' => 'nullable|string',
            'hardware_info.os' => 'nullable|string',
            'hardware_info.mac_address' => 'nullable|string'
        ];
    }
}