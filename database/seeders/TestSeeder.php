<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Country;
use App\Models\Asset;
use App\Models\Method;
use App\Models\SubMethod;
use App\Models\User;
use App\Models\Profile;
use App\Models\ProfilePhoto;
use App\Models\Transaction;
use App\Models\UserToken;
use App\Models\Wallet;

class TestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
{
    DB::transaction(function () {

        // Base Seeders
        $this->call([
            CountrySeeder::class,
            AssetSeeder::class,
            MethodSeeder::class,
        ]);

        $country = Country::where('name', 'Nigeria')->first()
            ?? Country::inRandomOrder()->first();

        /*
        |--------------------------------------------------------------------------
        | Admin User
        |--------------------------------------------------------------------------
        */
        $admin = User::create([
            'id'        => (string) Str::uuid(),
            'username'   => 'admin',
            'email'      => 'admin@example.com',
            'country_id' => $country?->id,
            'admin'      => true,
            'status'     => 'active',
            'two_factor' => true,
            'password'   => 'password',
            'profile_completed' => true,
            'email_verified_at' => now(),
        ]);

        Profile::create([
            'id'         => (string) Str::uuid(),
            'user_id'    => $admin->id,
            'first_name' => 'System',
            'last_name'  => 'Administrator',
            'gender'     => 'male',
            'phone'      => '+2348000000000',
            'state'      => 'Lagos',
            'address'    => 'Admin Office',
            'zip'        => '100001',
        ]);

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

        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */
        $users = collect();

        for ($i = 1; $i <= 10; $i++) {

            $user = User::create([
                'id'          => (string) Str::uuid(),
                'username'    => "user{$i}",
                'email'       => "user{$i}@example.com",
                'country_id'  => $country?->id,
                'status'      => 'active',
                'password'    => 'password',
                'profile_completed' => true,
                'email_verified_at' => now(),
            ]);

            Profile::create([
                'id'         => (string) Str::uuid(),
                'user_id'    => $user->id,
                'first_name' => fake()->firstName(),
                'last_name'  => fake()->lastName(),
                'gender'     => fake()->randomElement(['male','female']),
                'phone'      => fake()->phoneNumber(),
                'state'      => fake()->state(),
                'address'    => fake()->address(),
                'zip'        => fake()->postcode(),
                'dob'        => fake()->date(),
            ]);

            if ($i <= 5) {
                ProfilePhoto::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'img' => 'profiles/default-avatar.png',
                ]);
            }

            $users->push($user);
        }

        /*
        |--------------------------------------------------------------------------
        | Sub Methods 'method_id', 'name', 'account_name', 'account_number', 'bank_name', 'routing_number', 'swift_code', 'iban', 'wallet_address', 'network', 'instructions', 'is_active'
        |--------------------------------------------------------------------------
        */
        $methods = Method::pluck('id', 'name');

        $subMethods = collect([
            [
                'method_name' => 'Crytocurrency',
                'name' => 'Ethereum Mainnet',
                'wallet_address' => '0x0000000000000000000000000000000000000000',
                'network' => 'Ethereum',
                'instructions' => 'Use the Ethereum Mainnet for deposits and withdrawals.',
            ],

            [
                'method_name' => 'Crytocurrency',
                'name' => 'TRC20 Network',
                'wallet_address' => 'T-DEMO-ADDRESS-NOT-A-REAL-WALLET',
                'network' => 'Tron',
                'instructions' => 'Use the TRC20 network for USDT transactions.',
            ],

            [
                'method_name' => 'Bank Transfer',
                'name' => 'Standard Bank Transfer',
                'account_name' => 'Northfolio Demo Bank',
                'account_number' => '1234567890',
                'bank_name' => 'Example Bank',
                'routing_number' => '021000021',
                'instructions' => 'Use the provided bank account details when making deposits.',
            ],
        ])
        ->map(function ($subMethod) use ($methods) {

            return [
                'id' => (string) Str::uuid(),

                'method_id' => $methods->get($subMethod['method_name']),

                'name' => $subMethod['name'],

                'account_name' => $subMethod['account_name'] ?? null,
                'account_number' => $subMethod['account_number'] ?? null,
                'bank_name' => $subMethod['bank_name'] ?? null,

                'routing_number' => $subMethod['routing_number'] ?? null,
                'swift_code' => null,
                'iban' => null,

                'wallet_address' => $subMethod['wallet_address'] ?? null,
                'network' => $subMethod['network'] ?? null,

                'instructions' => $subMethod['instructions'],

                'is_active' => true,

                'created_at' => now(),
                'updated_at' => now(),
            ];
        })
        ->filter(fn ($subMethod) => filled($subMethod['method_id']))
        ->values()
        ->all();

        SubMethod::insert($subMethods);

        /*
        |--------------------------------------------------------------------------
        | Transactions
        |--------------------------------------------------------------------------
        */
        $assetIds = Asset::pluck('id')->toArray();
        $subMethodIds = SubMethod::pluck('id')->toArray();

        $wallets = collect();

        $demoPhrases = [];

        foreach ($users as $user) {

            // 💡 Define the phrase variable here first before using it below
            $phrase = \App\Support\Bip39::fromEntropy(substr(hash('sha256', 'northfolio-demo-' . $user->username, true), 0, 16));
            $demoPhrases[$user->username] = $phrase;

            $wallet = Wallet::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'mnemonic_hash' => Wallet::mnemonicHash($phrase),
                'fingerprint' => Wallet::fingerprint($phrase),
                'public_id' => Wallet::publicId($phrase),
            ]);

            $wallets->push($wallet);
        }

        // Demo prices so Buy works offline; the price-sync job overwrites them.
        $demoPrices = ['btc' => 60000, 'eth' => 3000, 'usdt' => 1, 'trx' => 0.12, 'bnb' => 580, 'xrp' => 0.55, 'aapl' => 190, 'tsla' => 240];
        foreach ($demoPrices as $name => $price) {
            Asset::where('name', $name)->whereNull('current_price')->update(['current_price' => $price]);
        }

        $usd = Asset::where('name', 'usd')->first();
        $priced = Asset::where('name', '!=', 'usd')->whereNotNull('current_price')->get();
        $subMethodIds = SubMethod::pluck('id')->toArray();
        $transactions = [];

        $row = fn (array $r) => $r + [
            'id' => (string) Str::uuid(),
            'sub_method_id' => null,
            'external_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Every row below is ledger-consistent: deposits fund USD, buys debit USD
        // for the asset credited, and withdrawals never exceed the balance.
        foreach ($wallets as $wallet) {
            $deposit = rand(1000, 5000);
            $buyUsd  = rand(200, 800);
            $asset   = $priced->random();
            $charge  = round($buyUsd * ((float) ($asset->buy_charge_rate ?? 0) / 100), 2);
            $net     = $buyUsd - $charge;
            $swapId  = (string) Str::uuid();

            $transactions[] = $row([
                'wallet_id' => $wallet->id, 'type' => 'deposit', 'asset_id' => $usd->id,
                'amount' => $deposit, 'status' => 'completed', 'reference' => (string) Str::uuid(),
                'external_id' => $pi = 'pi_seed_' . Str::random(14),
                'meta' => json_encode(['source' => 'seed', 'stripe_payment_intent_id' => $pi]),
            ]);

            $transactions[] = $row([
                'wallet_id' => $wallet->id, 'type' => 'swap', 'asset_id' => $usd->id,
                'amount' => $net, 'status' => 'completed', 'reference' => $swapId,
                'meta' => json_encode(['kind' => 'buy', 'direction' => 'outgoing', 'swap_id' => $swapId, 'to_asset_id' => $asset->id]),
            ]);
            $transactions[] = $row([
                'wallet_id' => $wallet->id, 'type' => 'swap', 'asset_id' => $asset->id,
                'amount' => round($net / (float) $asset->current_price, 8), 'status' => 'completed', 'reference' => $swapId,
                'meta' => json_encode(['kind' => 'buy', 'direction' => 'incoming', 'swap_id' => $swapId, 'from_asset_id' => $usd->id]),
            ]);
            if ($charge > 0) {
                $transactions[] = $row([
                    'wallet_id' => $wallet->id, 'type' => 'withdrawal', 'asset_id' => $usd->id,
                    'amount' => $charge, 'status' => 'completed', 'reference' => $swapId,
                    'meta' => json_encode(['swap_charge' => true, 'swap_id' => $swapId]),
                ]);
            }

            // One pending USD withdrawal awaiting admin review (demo payout).
            $transactions[] = $row([
                'wallet_id' => $wallet->id, 'type' => 'withdrawal', 'asset_id' => $usd->id,
                'amount' => rand(50, 150), 'status' => 'pending', 'reference' => 'demo-payout-' . Str::random(8),
                'sub_method_id' => Arr::random($subMethodIds),
                'meta' => json_encode(['destination' => 'demo-bank']),
            ]);
        }

        Transaction::insert($transactions);

        /*
        |--------------------------------------------------------------------------
        | Tokens
        |--------------------------------------------------------------------------
        */
        foreach ($users->take(5) as $user) {

            UserToken::create([
                'id'         => (string) Str::uuid(),
                'user_id'    => $user->id,
                'token'      => Str::random(64),
                'purpose'    => 'email_verification',
                'expires_at' => now()->addHour(),
            ]);

        }

        $this->command->info('Test data seeded successfully.');
        $this->command->warn('Demo recovery phrases (deterministic, public, demo-only):');
        foreach (array_slice($demoPhrases, 0, 3, true) as $name => $p) {
            $this->command->line("  {$name}: {$p}");
        }
    });
}
}
