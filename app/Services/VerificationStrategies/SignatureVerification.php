<?php

namespace App\Services\VerificationStrategies;

class SignatureVerification implements VerificationStrategyInterface
{
    public const ERROR_CODE = 'invalid_signature';

    public function verify(array $data): bool
    {
        $issuer = isset($data['data']['issuers']) ?
            $data['data']['issuers'][0] :
            ($data['data']['issuer'] ?? false);

        if (!$issuer) {
            return false;
        }

        $dataToHash = [
            "id" => $data['data']['id'],
            "name" => $data['data']['name'],
            "recipient.name" => $data['data']['recipient']['name'],
            "recipient.email" => $data['data']['recipient']['email'],
            "issuer.name" => $issuer['name'],
            "issuer.identityProof.type" => $issuer['identityProof']['type'],
            "issuer.identityProof.key" => $issuer['identityProof']['key'],
            "issuer.identityProof.location" => $issuer['identityProof']['location'],
            "issued" => $data['data']['issuedOn'] ?? $data['data']['issued'],
        ];

        foreach ($dataToHash as &$value) {
            $value = $this->sanitizeData($value);
        }

        ksort($dataToHash);

        $hashes = array_map(fn($key, $value) => hash('sha256', json_encode([$key => $value])), array_keys($dataToHash), $dataToHash);

        sort($hashes);

        $finalHash = hash('sha256', json_encode($hashes));

        return $finalHash === $data['signature']['targetHash'];
    }

    private function sanitizeData($value): bool|string|null
    {
        if (preg_match('/(?<=:string:).+$/', $value, $matches)) {
            return $matches[0];
        }

        return $value;
    }
}
