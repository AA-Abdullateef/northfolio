<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * BIP-39 mnemonic encoding (entropy ↔ words, with checksum).
 *
 * Implements the mnemonic sentence part of BIP-39 only (ENT → CS → 11-bit word
 * indexes). It does NOT implement PBKDF2 seed derivation or any key derivation,
 * because Northfolio never derives keys from the phrase (demo identity only).
 * Verified against the official BIP-39 test vectors in tests/Unit/Bip39Test.php.
 */
final class Bip39
{
    private const VALID_WORD_COUNTS = [12, 15, 18, 21, 24];

    /** @var list<string>|null */
    private static ?array $words = null;

    /** @return list<string> */
    public static function wordlist(): array
    {
        if (self::$words === null) {
            $list = file(resource_path('wordlists/bip39_english.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            if ($list === false || count($list) !== 2048) {
                throw new \RuntimeException('BIP-39 wordlist missing or not 2048 words.');
            }

            self::$words = array_values($list);
        }

        return self::$words;
    }

    /** Generate a mnemonic using the OS CSPRNG (random_bytes). */
    public static function generate(int $wordCount = 12): string
    {
        if (! in_array($wordCount, self::VALID_WORD_COUNTS, true)) {
            throw new InvalidArgumentException('Word count must be 12, 15, 18, 21 or 24.');
        }

        return self::fromEntropy(random_bytes((int) ($wordCount * 11 - $wordCount / 3) / 8));
    }

    /** Encode raw entropy (16/20/24/28/32 bytes) as a mnemonic. */
    public static function fromEntropy(string $entropy): string
    {
        $bytes = strlen($entropy);

        if ($bytes < 16 || $bytes > 32 || $bytes % 4 !== 0) {
            throw new InvalidArgumentException('Entropy must be 16–32 bytes in 4-byte steps.');
        }

        $entropyBits = self::toBits($entropy);
        $checksumLen = intdiv($bytes * 8, 32);
        $checksum    = substr(self::toBits(hash('sha256', $entropy, true)), 0, $checksumLen);
        $bits        = $entropyBits . $checksum;

        $list  = self::wordlist();
        $words = [];

        foreach (str_split($bits, 11) as $chunk) {
            $words[] = $list[bindec($chunk)];
        }

        return implode(' ', $words);
    }

    /** True when the phrase has a valid length, known words and a correct checksum. */
    public static function isValid(string $phrase): bool
    {
        $words = self::split($phrase);

        if (! in_array(count($words), self::VALID_WORD_COUNTS, true)) {
            return false;
        }

        $index = array_flip(self::wordlist());
        $bits  = '';

        foreach ($words as $word) {
            if (! isset($index[$word])) {
                return false;
            }
            $bits .= str_pad(decbin($index[$word]), 11, '0', STR_PAD_LEFT);
        }

        $checksumLen = intdiv(strlen($bits), 33);
        $entropyBits = substr($bits, 0, -$checksumLen);
        $checksum    = substr($bits, -$checksumLen);

        $entropy = '';
        foreach (str_split($entropyBits, 8) as $byte) {
            $entropy .= chr(bindec($byte));
        }

        $expected = substr(self::toBits(hash('sha256', $entropy, true)), 0, $checksumLen);

        return hash_equals($expected, $checksum);
    }

    /** @return list<string> */
    public static function split(string $phrase): array
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $phrase)));

        return $normalized === '' ? [] : explode(' ', $normalized);
    }

    private static function toBits(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        return $bits;
    }
}
