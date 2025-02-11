<?php

namespace App\Exceptions;

use Exception;

class EnvatoVerificationException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Envato license verification failed',
            'error' => $this->getMessage()
        ], 422);
    }
}