<?php

namespace Database\Seeders;

use App\Models\Method;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['name'      => 'Crytocurrency'],
            ['name'      => 'Bank Transfer'],
        ];

        foreach ($methods as $method) {
            Method::firstOrCreate(
                ['name' => $method['name']],
                array_merge($method, [
                    'id'     => (string) Str::uuid(),
                ])
            );
        }

        $this->command->info('Payment methods seeded (' . count($methods) . ').');
    }
}