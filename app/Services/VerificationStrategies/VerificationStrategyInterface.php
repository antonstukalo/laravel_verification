<?php

namespace App\Services\VerificationStrategies;

interface VerificationStrategyInterface
{
    public function verify(array $data): bool;
}
