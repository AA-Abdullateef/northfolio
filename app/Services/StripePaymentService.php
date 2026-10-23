<?php

namespace App\Services;

use App\Support\Money;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Stripe client (no SDK): creates PaymentIntents and verifies webhook signatures.
 * Intended for Stripe TEST mode — no real money moves.
 */
class StripePaymentService
{
    private const API = 'https://api.stripe.com/v1';
    private const TOLERANCE = 300; // seconds

    public function __construct(
        private ?string $secretKey = null,
        private ?string $webhookSecret = null,
    ) {
        $this->secretKey     ??= config('services.stripe.secret');
        $this->webhookSecret ??= config('services.stripe.webhook_secret');
    }

    /** @return array<string,mixed> the PaymentIntent object */
    public function createPaymentIntent(string $usdAmount, array $metadata = []): array
    {
        if (! $this->secretKey) {
            throw new RuntimeException('Stripe is not configured.');
        }

        $payload = [
            'amount'   => Money::toMinorUnits($usdAmount, 2),
            'currency' => 'usd',
            'automatic_payment_methods' => ['enabled' => 'true'],
        ] + $this->flattenMetadata($metadata);

        $res = Http::asForm()->withToken($this->secretKey)->post(self::API . '/payment_intents', $payload);

        if (! $res->successful()) {
            throw new RuntimeException('Stripe error: ' . ($res->json('error.message') ?? $res->status()));
        }

        return $res->json();
    }

    public function retrievePaymentIntent(string $id): array
    {
        if (! $this->secretKey) {
            throw new RuntimeException('Stripe is not configured.');
        }

        $res = Http::withToken($this->secretKey)->get(self::API . '/payment_intents/' . $id);

        if (! $res->successful()) {
            throw new RuntimeException('Stripe error: ' . ($res->json('error.message') ?? $res->status()));
        }

        return $res->json();
    }

    /**
     * Verify a `Stripe-Signature` header (t=...,v1=...) over the raw payload.
     */
    public function verifyWebhookSignature(string $payload, ?string $header): bool
    {
        if (! $this->webhookSecret) {
            throw new RuntimeException('Stripe webhook secret is not configured.');
        }

        if (! $header) {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($k === 't') {
                $timestamp = $v;
            } elseif ($k === 'v1' && $v !== null) {
                $signatures[] = $v;
            }
        }

        if (! $timestamp || ! ctype_digit($timestamp) || ! $signatures) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::TOLERANCE) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $this->webhookSecret);

        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }

    private function flattenMetadata(array $metadata): array
    {
        $out = [];
        foreach ($metadata as $k => $v) {
            $out["metadata[$k]"] = (string) $v;
        }

        return $out;
    }
}
