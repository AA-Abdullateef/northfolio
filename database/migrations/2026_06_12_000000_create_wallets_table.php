<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // wallets stores wallet identity material only. Mnemonics are never stored.
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mnemonic_hash', 64);
            $table->string('fingerprint', 64)->unique();
            $table->string('public_id', 64);
            $table->timestamps();

            $table->index('user_id');
            $table->index('mnemonic_hash');
            $table->index('public_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
