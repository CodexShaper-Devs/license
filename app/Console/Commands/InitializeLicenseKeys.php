<?php

namespace App\Console\Commands;

use App\Services\KeyManagementService;
use Illuminate\Console\Command;

class InitializeLicenseKeys extends Command
{
    protected $signature = 'license:init-keys';
    protected $description = 'Initialize license encryption keys';

    public function handle(KeyManagementService $keyManager)
    {
        try {
            // Generate master key if it doesn't exist
            if (!$keyManager->hasMasterKey()) {
                $this->info('Generating master key...');
                $keyManager->generateMasterKey();
            }

            // Generate encryption keys
            $this->info('Generating encryption keys...');
            $keyManager->generateKeyPair();

            $this->info('License encryption keys initialized successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to initialize keys: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}