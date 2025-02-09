<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use App\Models\License;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use App\Http\Requests\LicenseRequest;

class LicenseController extends Controller
{
    protected $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }

    public function index()
    {

        // // Set exact timestamp from requirement
        // Carbon::setTestNow(Carbon::parse('2025-02-06 08:43:39'));

        try {

            // $license = [
            //     "license_key" => "LICENSE-2025-IQCQ-FBZ0", 
            //     "device_identifier" => "SERVER-PROD-001", 
            //     "device_name" => "Production Server 1", 
            //     "hardware" => [
            //           "cpu_id" => "AMD-Ryzen-9-5950X", 
            //           "disk_id" => "Samsung-970-EVO-Plus-1TB", 
            //           "mac_address" => "00:1B:44:11:3A:B7" 
            //        ], 
            //     "domain" => "lms-hub.test", 
            //     "metadata" => [
            //              "os" => "Ubuntu 22.04 LTS", 
            //              "php_version" => "8.2.0", 
            //              "app_version" => "1.0.0", 
            //              "environment" => "production", 
            //              "server_ip" => "127.0.0.1", 
            //              "installed_at" => "2025-02-06 14:54:44", 
            //              "activated_by" => "maab16" 
            //           ] 
            //  ]; 

            //  $result = $this->licenseService->activateLicense(
            //     $license['license_key'],
            //     $license
            // );

            // dd($result);

            // DB::beginTransaction();

            // 1. Find or create user with specified login
            $user = User::firstOrCreate(
                ['email' => 'maab16@example.com'],
                [
                    'name' => 'maab16',
                    'email' => 'maab16@example.com',
                    'password' => bcrypt('password'), // You should change this in production
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 2. Create the product if it doesn't exist
            $product = Product::firstOrCreate(
                ['slug' => 'premium-invoice-manager'],
                [
                    'name' => 'Premium Invoice Manager',
                    'description' => 'Professional invoice management software for businesses',
                    'version' => '2.0.0',
                    'type' => 'software',
                    'price' => 299.99,
                    'is_active' => true,
                    'metadata' => [
                        'author' => 'maab16',
                        'website' => 'https://invoicemanager.com',
                        'support_email' => 'support@invoicemanager.com',
                        'minimum_php_version' => '8.1',
                        'release_date' => '2025-02-06',
                        'last_updated' => '2025-02-06 08:43:39'
                    ],
                    'settings' => [
                        'max_seats' => 10,
                        'trial_days' => 14,
                        'requires_domain_validation' => true,
                        'check_in_interval' => 7,
                        'allowed_offline_days' => 3,
                        'hardware_validation' => true
                    ],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // 3. Create license using the service
            $licenseService = app(LicenseService::class);

            $licenseData = [
                'user_id' => $user->id,
                'product_id' => $product->id,
                'type' => 'subscription',
                'status' => 'active',
                'seats' => 5,
                'valid_from' => now(),
                'valid_until' => now()->addYear(),
                'created_by' => 'maab16',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $license = $licenseService->createLicense($licenseData);

            // 4. Output the created license details
            echo "License Created Successfully\n";
            echo "===========================\n";
            echo "License Key: {$license->key}\n";
            echo "Created At: {$license->created_at}\n";
            echo "Valid Until: {$license->valid_until}\n";
            echo "Status: {$license->status}\n";
            echo "Seats: {$license->seats}\n";
            echo "\nFeatures:\n";
            foreach ($license->features as $feature => $config) {
                echo "- {$feature}: " . (is_array($config) ? 'Enabled' : $config) . "\n";
            }
            echo "\nRestrictions:\n";
            foreach ($license->restrictions as $key => $value) {
                echo "- {$key}: " . (is_bool($value) ? ($value ? 'Yes' : 'No') : $value) . "\n";
            }
            echo "\nMetadata:\n";
            foreach ($license->metadata as $key => $value) {
                if ($value) {
                    echo "- {$key}: {$value}\n";
                }
            }

            // 5. Create initial activation
            $activationData = [
                'license_key' => $license->key,
                'device_identifier' => 'SERVER-PROD-001-' . time(),
                'device_name' => 'Production Server 1',
                'hardware' => [
                    'cpu_id' => 'AMD-Ryzen-9-5950X-' . uniqid(),
                    'disk_id' => 'Samsung-970-EVO-Plus-1TB-' . uniqid(),
                    'mac_address' => '00:1B:44:' . implode(':', str_split(substr(md5(time()), 0, 6), 2))
                ],
                'domain' => 'mycompany.com',
                'metadata' => [
                    'os' => 'Ubuntu 22.04 LTS',
                    'php_version' => '8.2.0',
                    'app_version' => '2.0.0',
                    'environment' => 'production',
                    'server_ip' => '10.0.0.1',
                    'installation_path' => '/var/www/premium-invoice-manager',
                    'activated_at' => now()->toDateTimeString(),
                    'activated_by' => 'maab16'
                ]
            ];

            $activation = $licenseService->activateLicense($license->key, $activationData);

            echo "\nInitial Activation:\n";
            echo "==================\n";
            echo "Activation ID: {$activation['activation_id']}\n";
            echo "Device ID: {$activationData['device_identifier']}\n";
            echo "Check-in Required At: {$activation['check_in_required_at']}\n";

            // 6. Validate the license
            $validationResult = $licenseService->validateLicense($license->key, [
                'domain' => 'mycompany.com',
                'environment' => 'production',
                'device_identifier' => $activationData['device_identifier']
            ]);

            echo "\nLicense Validation:\n";
            echo "==================\n";
            echo "Valid: " . ($validationResult['valid'] ? 'Yes' : 'No') . "\n";
            echo "Expires In: " . now()->diffInDays($license->valid_until) . " days\n";

            // DB::commit();

            // 7. Generate client config
            echo "\nClient Configuration:\n";
            echo "===================\n";
            echo json_encode([
                'license_key' => $license->key,
                'api_endpoint' => config('app.url') . '/api/v1/licenses',
                'check_in_interval' => $license->settings['check_in_interval'] * 3600, // in seconds
                'offline_grace_period' => $license->settings['offline_grace_period'] * 3600, // in seconds
                'hardware_validation' => $license->settings['hardware_validation'],
                'strict_validation' => $license->settings['strict_validation'],
            ], JSON_PRETTY_PRINT) . "\n";

        } catch (\Exception $e) {
            DB::rollBack();
            echo "Error: {$e->getMessage()}\n";
            echo "Stack trace:\n{$e->getTraceAsString()}\n";
        }

        // $licenses = License::with(['user', 'activations'])->paginate(15);

        // var_dump($licenses);
        // return view('licenses.index', compact('licenses'));
    }

    public function store(LicenseRequest $request)
    {
        $license = $this->licenseService->createLicense($request->validated());
        return response()->json($license, 201);
    }

    public function activate(Request $request, License $license)
    {
        $request->validate([
            'device_identifier' => 'required|string',
            'device_name' => 'required|string',
        ]);

        $deviceData = $request->only(['device_identifier', 'device_name']);
        $deviceData['ip_address'] = $request->ip();

        $activation = $this->licenseService->activateLicense($license, $deviceData);
        return response()->json($activation, 201);
    }

    public function validate(Request $request)
    {
        $request->validate([
            'license_key' => 'required|string',
            'device_identifier' => 'required|string',
        ]);

        $isValid = $this->licenseService->validateLicense(
            $request->license_key,
            $request->device_identifier
        );

        return response()->json(['valid' => $isValid]);
    }

    public function deactivate(Request $request, License $license)
    {
        $request->validate(['device_identifier' => 'required|string']);

        $success = $this->licenseService->deactivateLicense(
            $license,
            $request->device_identifier
        );

        return response()->json(['success' => $success]);
    }
}