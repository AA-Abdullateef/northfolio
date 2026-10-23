<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ledger precision: 8 decimal places for every asset (was 5, while
        // fees/conversions were computed to 8 — values silently truncated).
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('amount', 24, 8)->change();
            // Provider reference (e.g. Stripe PaymentIntent id). UNIQUE so one
            // provider payment can never back two ledger rows.
            $table->string('external_id', 191)->nullable()->unique();
        });

        // Per-asset input precision (USD = cents, everything else 8 dp).
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedTinyInteger('decimals')->default(8);
        });
        DB::table('assets')->whereRaw('lower(name) = ?', ['usd'])->update(['decimals' => 2]);

        // Backfill external_id for existing Stripe deposits.
        DB::table('transactions')->where('type', 'deposit')->orderBy('id')->each(function ($row) {
            $meta = json_decode($row->meta ?? '[]', true) ?: [];
            if (! empty($meta['stripe_payment_intent_id'])) {
                DB::table('transactions')->where('id', $row->id)->update(['external_id' => $meta['stripe_payment_intent_id']]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', fn (Blueprint $t) => $t->dropColumn('decimals'));
        Schema::table('transactions', function (Blueprint $t) {
            $t->dropUnique(['external_id']);
            $t->dropColumn('external_id');
            // amount is intentionally left at (24,8): narrowing could truncate data.
        });
    }
};
