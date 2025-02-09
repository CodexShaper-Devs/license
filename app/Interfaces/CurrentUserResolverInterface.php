<?php

namespace App\Interfaces;

interface CurrentUserResolverInterface
{
    public function getId(): ?string;
    public function getIp(): ?string;
    public function getUserAgent(): ?string;
}