<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySystemRequirements extends Command
{
    protected $signature = 'license:verify-requirements';
    protected $description = 'Verify system requirements for license system';

    public function handle()
    {
        $this->info('Checking system requirements...');

        // Check PHP version
        $this->info('PHP Version: ' . PHP_VERSION);
        
        // Check OpenSSL
        if (extension_loaded('openssl')) {
            $this->info('✓ OpenSSL extension is loaded');
            $this->info('OpenSSL Version: ' . OPENSSL_VERSION_TEXT);
        } else {
            $this->error('✗ OpenSSL extension is not loaded');
        }

        // Check random_bytes availability
        try {
            random_bytes(32);
            $this->info('✓ random_bytes is available');
        } catch (\Exception $e) {
            $this->error('✗ random_bytes is not available: ' . $e->getMessage());
        }

        // Check directory permissions
        $directories = [
            storage_path('app/keys'),
            storage_path('app/license')
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (is_writable($dir)) {
                $this->info("✓ Directory is writable: $dir");
            } else {
                $this->error("✗ Directory is not writable: $dir");
            }
        }

        // Verify composer dependencies
        if (class_exists('\ParagonIE\HiddenString\HiddenString')) {
            $this->info('✓ HiddenString class is available');
        } else {
            $this->error('✗ HiddenString class is not available');
        }

        if (class_exists('\phpseclib3\Crypt\RSA')) {
            $this->info('✓ phpseclib3 RSA class is available');
        } else {
            $this->error('✗ phpseclib3 RSA class is not available');
        }

        $this->info("\nSystem verification complete!");
    }
}