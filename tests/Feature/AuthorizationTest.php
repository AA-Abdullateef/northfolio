<?php

namespace Tests\Feature;

use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsLedger;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase, BuildsLedger;

    public function test_money_endpoints_require_authentication(): void
    {
        foreach (['me', 'dashboard', 'transactions', 'deposits/options', 'buy/options', 'withdrawals/options'] as $path) {
            $this->getJson("/api/v1/{$path}")->assertStatus(401);
        }
        foreach (['deposits', 'withdrawals', 'transfers', 'swaps', 'buys', 'sells'] as $path) {
            $this->postJson("/api/v1/{$path}", [])->assertStatus(401);
        }
    }

    public function test_a_wallet_only_sees_its_own_transactions(): void
    {
        $usd = $this->usd();
        $alice = $this->wallet();
        $bob   = $this->wallet();
        $mine  = $this->fund($alice, $usd, '10');
        $theirs = $this->fund($bob, $usd, '20');

        Sanctum::actingAs($alice);
        $ids = collect($this->getJson('/api/v1/transactions')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_a_wallet_cannot_read_another_wallets_deposit(): void
    {
        $usd = $this->usd();
        $alice = $this->wallet();
        $bob   = $this->wallet();
        $dep = Transaction::create(['wallet_id' => $bob->id, 'asset_id' => $usd->id, 'type' => 'deposit', 'amount' => '5', 'status' => 'pending', 'reference' => 'r', 'external_id' => 'pi_own']);

        Sanctum::actingAs($alice);
        $this->getJson("/api/v1/deposits/{$dep->id}")->assertStatus(403);

        Sanctum::actingAs($bob);
        $this->getJson("/api/v1/deposits/{$dep->id}")->assertOk();
    }

    public function test_wallet_lookup_exposes_no_personal_data_and_rejects_self(): void
    {
        [$user, $alice] = $this->userWithWallet();
        [, $bob] = $this->userWithWallet();

        Sanctum::actingAs($alice);
        $res = $this->getJson('/api/v1/wallet/lookup?query=' . $bob->public_id)->assertOk();

        $this->assertEqualsCanonicalizing(['id', 'fingerprint', 'public_id'], array_keys($res->json('data.wallet')));
        $this->getJson('/api/v1/wallet/lookup?query=' . $alice->public_id)->assertStatus(422);
        $this->getJson('/api/v1/wallet/lookup?query=' . str_repeat('0', 64))->assertStatus(404);
    }

    public function test_admin_panel_is_invisible_to_regular_users_and_guests(): void
    {
        $user  = $this->user(['admin' => false]);
        $admin = $this->user(['admin' => true]);

        foreach (['admin.dashboard', 'admin.users.index', 'admin.transactions.index', 'admin.wallets.index', 'admin.assets.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('admin.login'));                  // guest
            $this->actingAs($user, 'admin')->get(route($route))->assertNotFound();            // signed in, not admin
            $this->actingAs($admin, 'admin')->get(route($route))->assertOk();
            auth('admin')->logout();
        }
    }

    public function test_price_sync_cron_endpoint_requires_the_shared_secret(): void
    {
        config(['services.price_sync.key' => 'right-key']);

        $this->get('/admin/assets/sync-prices/run')->assertForbidden();
        $this->get('/admin/assets/sync-prices/run?key=wrong')->assertForbidden();

        config(['services.price_sync.key' => '']);
        $this->get('/admin/assets/sync-prices/run?key=')->assertForbidden();   // unset secret fails closed
    }

    public function test_recovery_phrases_are_never_returned_by_authenticated_endpoints(): void
    {
        [$user, $wallet] = $this->userWithWallet();
        $this->usd();

        Sanctum::actingAs($wallet);
        $dash = $this->getJson('/api/v1/dashboard')->assertOk()->json('data.wallet');
        $me   = $this->getJson('/api/v1/me')->assertOk()->json('data.wallet');

        $this->assertArrayNotHasKey('mnemonic', $dash);
        $this->assertArrayNotHasKey('mnemonic', $me);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('wallets', 'mnemonic'));
    }
}
