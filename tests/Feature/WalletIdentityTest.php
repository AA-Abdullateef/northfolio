<?php

namespace Tests\Feature;

use App\Models\Wallet;
use App\Support\Bip39;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WalletIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function generate(): array
    {
        return $this->postJson('/api/v1/wallet/generate')->assertOk()->json('data');
    }

    private function challenge(string $token): array
    {
        return $this->postJson('/api/v1/wallet/challenge', ['setup_token' => $token])->assertOk()->json('data.positions');
    }

    private function answers(string $phrase, array $positions): array
    {
        $words = explode(' ', $phrase);

        return collect($positions)->mapWithKeys(fn ($p) => [(string) $p => $words[$p - 1]])->all();
    }

    public function test_generated_phrase_is_a_valid_bip39_mnemonic(): void
    {
        $data = $this->generate();

        $this->assertTrue(Bip39::isValid($data['mnemonic']));
        $this->assertCount(12, explode(' ', $data['mnemonic']));
        $this->assertNotSame($data['mnemonic'], $this->generate()['mnemonic']);
    }

    public function test_challenge_asks_for_three_distinct_sorted_positions(): void
    {
        $positions = $this->challenge($this->generate()['setup_token']);

        $this->assertCount(3, $positions);
        $this->assertSame($positions, array_values(array_unique($positions)));
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
        $this->assertSame([], array_diff($positions, range(1, 12)));
    }

    public function test_correct_answers_create_a_wallet_without_persisting_the_phrase(): void
    {
        $data = $this->generate();
        $pos  = $this->challenge($data['setup_token']);

        $res = $this->postJson('/api/v1/wallet/verify', ['setup_token' => $data['setup_token'], 'words' => $this->answers($data['mnemonic'], $pos)])
            ->assertStatus(201);

        $this->assertNotEmpty($res->json('data.access_token'));
        $wallet = Wallet::first();
        $this->assertSame(Wallet::fingerprint($data['mnemonic']), $wallet->fingerprint);
        $this->assertSame(Wallet::publicId($data['mnemonic']), $wallet->public_id);
        $this->assertFalse(Schema::hasColumn('wallets', 'mnemonic'));

        // No stored value anywhere in the wallets row contains the phrase (or any 3 consecutive words of it).
        $row = json_encode(DB::table('wallets')->first());
        $words = explode(' ', $data['mnemonic']);
        $this->assertStringNotContainsString(implode(' ', array_slice($words, 0, 3)), $row);
        $this->assertStringNotContainsString($data['mnemonic'], json_encode($res->json()));
    }

    public function test_setup_token_is_single_use(): void
    {
        $data = $this->generate();
        $pos  = $this->challenge($data['setup_token']);
        $body = ['setup_token' => $data['setup_token'], 'words' => $this->answers($data['mnemonic'], $pos)];

        $this->postJson('/api/v1/wallet/verify', $body)->assertStatus(201);
        $this->postJson('/api/v1/wallet/verify', $body)->assertStatus(422);
        $this->assertSame(1, Wallet::count());
    }

    public function test_verify_before_challenge_is_refused(): void
    {
        $data = $this->generate();

        $this->postJson('/api/v1/wallet/verify', ['setup_token' => $data['setup_token'], 'words' => ['1' => 'a', '2' => 'b', '3' => 'c']])->assertStatus(422);
        $this->assertSame(0, Wallet::count());
    }

    public function test_wrong_answers_are_rejected_and_five_failures_burn_the_token(): void
    {
        $data = $this->generate();
        $pos  = $this->challenge($data['setup_token']);
        $bad  = collect($pos)->mapWithKeys(fn ($p) => [(string) $p => 'zoo'])->all();
        if (in_array('zoo', explode(' ', $data['mnemonic']), true)) {
            $bad = collect($pos)->mapWithKeys(fn ($p) => [(string) $p => 'abandon'])->all();
        }

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/wallet/verify', ['setup_token' => $data['setup_token'], 'words' => $bad])->assertStatus(422);
        }
        $this->postJson('/api/v1/wallet/verify', ['setup_token' => $data['setup_token'], 'words' => $bad])->assertStatus(422);   // 5th: token destroyed

        // Even the correct answers no longer work.
        $this->postJson('/api/v1/wallet/verify', ['setup_token' => $data['setup_token'], 'words' => $this->answers($data['mnemonic'], $pos)])->assertStatus(422);
        $this->assertSame(0, Wallet::count());
    }

    public function test_setup_token_expires_after_twenty_minutes(): void
    {
        $data = $this->generate();
        $pos  = $this->challenge($data['setup_token']);

        $this->travel(21)->minutes();

        $this->postJson('/api/v1/wallet/verify', ['setup_token' => $data['setup_token'], 'words' => $this->answers($data['mnemonic'], $pos)])->assertStatus(422);
        $this->postJson('/api/v1/wallet/challenge', ['setup_token' => $data['setup_token']])->assertStatus(422);
        $this->assertSame(0, Wallet::count());
    }

    public function test_import_signs_in_with_a_valid_known_phrase_and_rejects_everything_else(): void
    {
        $phrase = Bip39::generate();
        Wallet::forceCreate([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'mnemonic_hash' => Wallet::mnemonicHash($phrase), 'fingerprint' => Wallet::fingerprint($phrase), 'public_id' => Wallet::publicId($phrase),
        ]);

        $this->postJson('/api/v1/wallet/import', ['mnemonic' => strtoupper($phrase)])->assertOk()->assertJsonPath('data.auth_type', 'wallet');

        $words = explode(' ', $phrase);
        $words[11] = $words[11] === 'zoo' ? 'abandon' : 'zoo';                       // very likely breaks the checksum
        $bad = implode(' ', $words);
        if (! Bip39::isValid($bad)) {
            $this->postJson('/api/v1/wallet/import', ['mnemonic' => $bad])->assertStatus(422);
        }
        $this->postJson('/api/v1/wallet/import', ['mnemonic' => 'hello world'])->assertStatus(422);
        $this->postJson('/api/v1/wallet/import', ['mnemonic' => Bip39::generate()])->assertStatus(422)   // valid phrase, unknown wallet
            ->assertJsonPath('errors.mnemonic.0', 'No account found for this mnemonic phrase.');
    }
}
