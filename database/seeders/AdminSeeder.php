<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@northfolio.test')],
            [
                'id'                => (string) Str::uuid(),
                'username'          => env('ADMIN_USERNAME', 'admin'),
                'country_id'        => 'US',
                'password'          => bcrypt(env('ADMIN_PASSWORD', 'password')),
                'admin'             => true,
                'email_verified_at' => now(),
                'profile_completed' => true,
            ]
        );

        Profile::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'id'         => (string) Str::uuid(),
                'first_name' => 'Platform',
                'last_name'  => 'Admin',
            ]
        );

        // Create wallet if it doesn't exist
        $phrase = 'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about';

        Wallet::firstOrCreate(
            ['user_id' => $admin->id], // lookup field
            [
                'id'              => (string) Str::uuid(),
                'mnemonic_hash'   => Wallet::mnemonicHash($phrase),
                'fingerprint'     => Wallet::fingerprint($phrase),
                'public_id'      => Wallet::publicId($phrase),
            ]
        );

        $this->command->info("Admin user ready: {$admin->email}");
    }
}