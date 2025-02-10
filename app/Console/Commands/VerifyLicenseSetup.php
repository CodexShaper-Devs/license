<?php

namespace App\Console\Commands;

use App\Services\EncryptionService;
use App\Services\KeyManagementService;
use Illuminate\Console\Command;

class VerifyLicenseSetup extends Command
{
    protected $signature = 'license:verify-setup';
    protected $description = 'Verify license system setup';

    public function handle(
        KeyManagementService $keyManager,
        EncryptionService $encryption
    ) {
        $this->info('Checking license system setup...');

        try {
            // Check directories
            $this->checkDirectories();

            // Check master key
            if (!$keyManager->hasMasterKey()) {
                throw new \Exception('Master key not found');
            }

            // Verify encryption
            $testData = 'Test encryption';
            $encrypted = $encryption->encrypt($testData);
            $decrypted = $encryption->decrypt($encrypted);

            if ($testData !== $decrypted) {
                throw new \Exception('Encryption verification failed');
            }

            $this->info('✓ License system setup verified successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Setup verification failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function checkDirectories(): void
    {
        $directories = [
            storage_path('app/keys'),
            storage_path('app/license')
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                throw new \Exception("Directory not found: $dir");
            }
            if (!is_writable($dir)) {
                throw new \Exception("Directory not writable: $dir");
            }
        }
    }
}