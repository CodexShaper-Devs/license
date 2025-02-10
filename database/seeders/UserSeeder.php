<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    private const TIMESTAMP = '2025-02-09 16:02:50';
    private const USER = 'maab16';

    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
            'created_by' => self::USER,
            'email_verified_at' => self::TIMESTAMP,
        ]);

        // Create regular user
        User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'is_admin' => false,
            'created_by' => self::USER,
            'email_verified_at' => self::TIMESTAMP,
        ]);
    }
}