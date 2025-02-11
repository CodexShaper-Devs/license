<?php

namespace App\Exceptions;

use Exception;

class EnvatoActivationException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Envato license activation failed',
            'error' => $this->getMessage()
        ], 422);
    }
}