<?php

namespace App\Services\VerificationStrategies;

class RecipientVerification implements VerificationStrategyInterface
{
    public const ERROR_CODE = 'invalid_recipient';

    public function verify(array $data): bool
    {
        return isset($data['data']['recipient']['name'], $data['data']['recipient']['email']);
    }
}
