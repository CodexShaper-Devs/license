<?php

namespace Tests\Feature\Licenses;

use App\Services\LicenseCommunicationService;
use ParagonIE\Halite\KeyFactory;

class LicenseCommunicationTest extends BaseLicenseTest
{
    private LicenseCommunicationService $communicationService;

    protected function setUp(): void
    {
        parent::setUp();

        $encryptionKey = KeyFactory::generateEncryptionKey()->getRawKeyMaterial();
        $authKey = KeyFactory::generateAuthenticationKey()->getRawKeyMaterial();
        
        $this->communicationService = new LicenseCommunicationService(
            $encryptionKey,
            $authKey
        );
    }

    /** @test */
    public function it_can_encrypt_and_decrypt_license_data()
    {
        $license = $this->createTestLicense();
        
        $data = [
            'license_key' => $license->key,
            'hardware_hash' => 'test-hash',
            'timestamp' => now()->timestamp
        ];

        // Encrypt
        $encrypted = $this->communicationService->encryptResponse($data);
        $this->assertIsString($encrypted);

        // Decrypt
        $decrypted = $this->communicationService->decryptRequest($encrypted);
        $this->assertEquals($data, $decrypted);
    }

    /** @test */
    public function it_can_sign_and_verify_license_data()
    {
        $license = $this->createTestLicense();
        
        $data = [
            'license_key' => $license->key,
            'timestamp' => now()->timestamp
        ];

        // Sign
        $signature = $this->communicationService->signData($data);
        $this->assertIsString($signature);

        // Verify
        $isValid = $this->communicationService->verifySignature($data, $signature);
        $this->assertTrue($isValid);
    }
}