<?php

namespace Tests\Unit;

use App\Support\Bip39;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class Bip39Test extends TestCase
{
    /** Official vectors from https://github.com/trezor/python-mnemonic/blob/master/vectors.json */
    public static function vectors(): array
    {
        return [
            ['00000000000000000000000000000000', 'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about'],
            ['7f7f7f7f7f7f7f7f7f7f7f7f7f7f7f7f', 'legal winner thank year wave sausage worth useful legal winner thank yellow'],
            ['80808080808080808080808080808080', 'letter advice cage absurd amount doctor acoustic avoid letter advice cage above'],
            ['ffffffffffffffffffffffffffffffff', 'zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo zoo wrong'],
            ['0000000000000000000000000000000000000000000000000000000000000000', 'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon art'],
        ];
    }

    #[DataProvider('vectors')]
    public function test_matches_official_vectors(string $entropyHex, string $expected): void
    {
        $this->assertSame($expected, Bip39::fromEntropy(hex2bin($entropyHex)));
        $this->assertTrue(Bip39::isValid($expected));
    }

    public function test_generate_produces_valid_unique_phrases(): void
    {
        $a = Bip39::generate();
        $b = Bip39::generate();

        $this->assertCount(12, explode(' ', $a));
        $this->assertTrue(Bip39::isValid($a));
        $this->assertNotSame($a, $b);
        $this->assertTrue(Bip39::isValid(Bip39::generate(24)));
    }

    public function test_bad_checksum_unknown_word_and_wrong_length_are_rejected(): void
    {
        $this->assertFalse(Bip39::isValid('abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon'));
        $this->assertFalse(Bip39::isValid('abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon notaword'));
        $this->assertFalse(Bip39::isValid('abandon abandon abandon'));
        $this->assertFalse(Bip39::isValid(''));
    }

    public function test_validation_is_case_and_whitespace_insensitive(): void
    {
        $this->assertTrue(Bip39::isValid("  ABANDON abandon  abandon abandon abandon abandon abandon abandon abandon abandon abandon ABOUT\n"));
    }

    public function test_wordlist_is_the_official_2048_words(): void
    {
        $this->assertSame('2f5eed53a4727b4bf8880d8f3f199efc90e58503646d9ff8eff3a2ed3b24dbda', hash_file('sha256', resource_path('wordlists/bip39_english.txt')));
    }
}
