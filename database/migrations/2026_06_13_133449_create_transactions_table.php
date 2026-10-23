<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('wallet_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUuid('asset_id')
                ->nullable()
                ->constrained('assets')
                ->nullOnDelete();

            $table->foreignUuid('sub_method_id')
                ->nullable()
                ->constrained('sub_methods')
                ->nullOnDelete();

            $table->decimal('amount', 15, 5);

            $table->string('type', 20);

            $table->string('status')->default('pending');

            $table->string('reference', 191)->default('none');

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['wallet_id', 'type', 'status']);
            $table->index(['wallet_id', 'asset_id', 'type', 'status']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
