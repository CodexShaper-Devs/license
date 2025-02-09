<?php

namespace App\Services;

use App\Interfaces\CurrentUserResolverInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class CurrentUserResolverService implements CurrentUserResolverInterface
{
    public function getId(): ?string
    {
        return Auth::id() ? (string) Auth::id() : null;
    }

    public function getIp(): ?string
    {
        return Request::ip();
    }

    public function getUserAgent(): ?string
    {
        return Request::userAgent();
    }
}