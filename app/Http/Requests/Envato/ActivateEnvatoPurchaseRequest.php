<?php

namespace App\Http\Requests\Envato;

use Illuminate\Foundation\Http\FormRequest;

class ActivateEnvatoPurchaseRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'purchase_code' => 'required|string|min:23|max:36',
            'item_id' => 'required|string',
            'domain' => 'required|string|max:255',
            'device_identifier' => 'required|string|max:255',
            'device_name' => 'required|string|max:255',
            'hardware_info' => 'required|array',
            'hardware_info.cpu' => 'required|string',
            'hardware_info.ram' => 'required|string',
            'hardware_info.os' => 'required|string',
            'hardware_info.mac_address' => 'required|string'
        ];
    }
}