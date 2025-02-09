<?php

namespace App\Interfaces;

interface Licensable
{
    public function isValid(): bool;
    public function hasAvailableSeats(): bool;
    public function hasFeature(string $feature): bool;
    public function validateRestrictions(array $context): bool;
}