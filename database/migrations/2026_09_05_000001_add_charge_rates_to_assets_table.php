<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add per-asset charge rates for swap, buy, and sell operations.
     *
     * Stored as a percentage (e.g. 1.50 means 1.5%).
     * Nullable — null means no charge configured; the service layer treats null as 0.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // % charge deducted from the source asset on a swap.
            $table->decimal('swap_charge_rate', 5, 2)->nullable()->default(null)->after('price_updated_at');

            // % charge deducted from the fiat amount on a buy.
            $table->decimal('buy_charge_rate', 5, 2)->nullable()->default(null)->after('swap_charge_rate');

            // % charge deducted from the gross fiat payout on a sell.
            $table->decimal('sell_charge_rate', 5, 2)->nullable()->default(null)->after('buy_charge_rate');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['swap_charge_rate', 'buy_charge_rate', 'sell_charge_rate']);
        });
    }
};
