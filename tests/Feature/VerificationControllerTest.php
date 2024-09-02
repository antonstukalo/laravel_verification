<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use App\Services\VerificationService;
use App\Services\VerificationStrategies\RecipientVerification;
use App\Services\VerificationStrategies\IssuerVerification;
use App\Services\VerificationStrategies\SignatureVerification;
use GuzzleHttp\Client;

class VerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $verificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verificationService = new VerificationService();
        $this->verificationService->addStrategy(new RecipientVerification());
        $this->verificationService->addStrategy(new IssuerVerification(new Client()));
        $this->verificationService->addStrategy(new SignatureVerification());
    }

    public function test_verify_success()
    {
        $data = [
            "data" => [
                "id" => "63c79bd9303530645d1cca00",
                "name" => "Certificate of Completion",
                "recipient" => [
                    "name" => "Marty McFly",
                    "email" => "marty.mcfly@gmail.com"
                ],
                "issuer" => [
                    "name" => "Accredify",
                    "identityProof" => [
                        "type" => "DNS-DID",
                        "key" => "did:ethr:0x05b642ff12a4ae545357d82ba4f786f3aed84214#controller",
                        "location" => "ropstore.accredify.io"
                    ]
                ],
                "issued" => "2022-12-23T00:00:00+08:00"
            ],
            "signature" => [
                "type" => "SHA3MerkleProof",
                "targetHash" => "288f94aadadf486cfdad84b9f4305f7d51eac62db18376d48180cc1dd2047a0e"
            ]
        ];

        $response = $this->postJson('/api/verify', $data);

        $response->assertStatus(200);
        $response->assertJson(['data' => ['result' => 'verified']]);
    }

    public function test_verify_invalid_recipient()
    {
        $data = [
            'data' => [
                'id' => '63c79bd9303530645d1cca00',
                'name' => 'Certificate of Completion',
                'recipient' => [
                    'email' => 'marty.mcfly@gmail.com'
                ],
                'issuer' => [
                    'name' => 'Accredify',
                    'identityProof' => [
                        'type' => 'DNS-DID',
                        'key' => 'did:ethr:0x05b642ff12a4ae545357d82ba4f786f3aed84214#controller',
                        'location' => 'ropstore.accredify.io'
                    ]
                ],
                'issued' => '2022-12-23T00:00:00+08:00',
            ],
            'signature' => [
                'targetHash' => Hash::make('samplehash')
            ]
        ];

        $response = $this->postJson('/api/verify', $data);

        $response->assertStatus(200);
        $response->assertJson(['data' => ['result' => 'invalid_recipient']]);
    }

    public function test_verify_invalid_issuer()
    {
        $data = [
            'data' => [
                'id' => '63c79bd9303530645d1cca00',
                'name' => 'Certificate of Completion',
                'recipient' => [
                    'name' => 'Marty McFly',
                    'email' => 'marty.mcfly@gmail.com'
                ],
                'issuer' => [
                    'name' => 'Accredify',
                    'identityProof' => [
                        'type' => 'DNS-DID',
                        'key' => 'did:ethr:0xInvalidKeyHere#controller',
                        'location' => 'ropstore.accredify.io'
                    ]
                ],
                'issued' => '2022-12-23T00:00:00+08:00',
            ],
            'signature' => [
                'targetHash' => Hash::make('samplehash')
            ]
        ];

        $response = $this->postJson('/api/verify', $data);

        $response->assertStatus(200);
        $response->assertJson(['data' => ['result' => 'invalid_issuer']]);
    }

    public function test_verify_invalid_signature()
    {
        $data = [
            'data' => [
                'id' => '63c79bd9303530645d1cca00',
                'name' => 'Certificate of Completion',
                'recipient' => [
                    'name' => 'Marty McFly',
                    'email' => 'marty.mcfly@gmail.com'
                ],
                'issuer' => [
                    'name' => 'Accredify',
                    'identityProof' => [
                        'type' => 'DNS-DID',
                        'key' => 'did:ethr:0x05b642ff12a4ae545357d82ba4f786f3aed84214#controller',
                        'location' => 'ropstore.accredify.io'
                    ]
                ],
                'issued' => '2022-12-23T00:00:00+08:00',
            ],
            'signature' => [
                'targetHash' => 'invalidhashvalue000000000000000000000000000000000000000000000000000'
            ]
        ];

        $response = $this->postJson('/api/verify', $data);

        $response->assertStatus(200);
        $response->assertJson(['data' => ['result' => 'invalid_signature']]);
    }
}
