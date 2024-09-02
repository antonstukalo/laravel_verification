<?php

namespace App\Services;

use App\Services\VerificationStrategies\VerificationStrategyInterface;
use App\Models\Verification;

class VerificationService
{
    protected array $strategies = [];

    public function addStrategy(VerificationStrategyInterface $strategy): void
    {
        $this->strategies[] = $strategy;
    }

    public function verify(array $data): array
    {
        foreach ($this->strategies as $strategy) {
            if (!$strategy->verify($data)) {
                return ['success' => false, 'result' => $strategy::ERROR_CODE];
            }
        }

        return ['success' => true];
    }

    public function storeVerificationResult(?int $userId, string $fileType, string $result): void
    {
        Verification::create([
            'user_id' => $userId,
            'file_type' => $fileType,
            'verification_result' => $result,
        ]);
    }
}
