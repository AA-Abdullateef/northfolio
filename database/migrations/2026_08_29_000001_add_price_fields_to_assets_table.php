<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Which provider (if any) supplies this asset's USD price, and the
            // identifier to look it up by there (CoinGecko coin id, AlphaVantage
            // ticker symbol). Null/'manual' means the asset isn't auto-priced
            // (e.g. USD itself, which is always 1:1).
            $table->string('price_source', 20)->nullable()->after('active');
            $table->string('price_source_id', 50)->nullable()->after('price_source');

            $table->decimal('current_price', 24, 8)->nullable()->after('price_source_id');
            $table->timestamp('price_updated_at')->nullable()->after('current_price');
            $table->index(['active', 'price_source'], 'assets_active_price_source_index');
        });

        DB::table('assets')
            ->where('name', 'usd')
            ->update([
                'price_source' => 'manual',
                'current_price' => 1,
                'price_updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex('assets_active_price_source_index');
            $table->dropColumn(['price_source', 'price_source_id', 'current_price', 'price_updated_at']);
        });
    }
};
