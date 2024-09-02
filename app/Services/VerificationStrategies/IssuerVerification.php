<?php

namespace App\Services\VerificationStrategies;

use GuzzleHttp\Client;

class IssuerVerification implements VerificationStrategyInterface
{
    public const ERROR_CODE = 'invalid_issuer';

    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function verify(array $data): bool
    {
        $issuer = isset($data['data']['issuers']) ?
            $data['data']['issuers'][0] :
            ($data['data']['issuer'] ?? false);

        if (!$issuer) {
            return false;
        }

        $locationParts = parse_url($issuer['identityProof']['location']);
        $domain = trim($locationParts['path'] ?? $issuer['identityProof']['location'], '"');

        if (str_starts_with($domain, 'string:')) {
            $domain = substr($domain, 7);
        }

        $response = $this->client->get("https://dns.google/resolve", [
            'query' => [
                'name' => $domain,
                'type' => 'TXT'
            ]
        ]);

        $dnsData = json_decode($response->getBody(), true);

        if (isset($dnsData['Answer'])) {
            $key = $issuer['identityProof']['key'];

            if (preg_match('/(?<=:string:).+$/', $issuer['identityProof']['key'], $matches)) {
                $key = $matches[0];
            }

            foreach ($dnsData['Answer'] as $record) {
                $txtRecord = is_array($record['data'])
                    ? implode('', array_map(fn($item) => trim($item, '"'), $record['data']))
                    : trim($record['data'], '"');

                if (str_contains($txtRecord, $key)) {
                    return true;
                }
            }
        }

        return false;
    }
}
