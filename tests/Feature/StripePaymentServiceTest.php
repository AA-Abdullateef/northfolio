<?php

namespace Tests\Feature;

use App\Services\StripePaymentService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class StripePaymentServiceTest extends TestCase
{
    private function sign(string $payload, string $secret, ?int $t = null): string
    {
        $t ??= time();

        return "t={$t},v1=" . hash_hmac('sha256', "{$t}.{$payload}", $secret);
    }

    public function test_payment_intent_sends_cents_and_metadata(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'pi_1', 'client_secret' => 's'], 200)]);

        (new StripePaymentService('sk_test_x', 'whsec_x'))->createPaymentIntent('12.34', ['transaction_id' => 'abc']);

        Http::assertSent(fn ($r) => $r['amount'] == 1234
            && $r['currency'] === 'usd'
            && $r['metadata[transaction_id]'] === 'abc'
            && $r->hasHeader('Authorization', 'Bearer sk_test_x'));
    }

    public function test_amounts_convert_to_cents_without_float_error(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'pi_1'], 200)]);
        $svc = new StripePaymentService('sk_test_x');

        // Classic float traps: 0.29*100, 1.15*100, 19.99*100 are not whole in binary floats.
        foreach (['0.29' => 29, '1.15' => 115, '19.99' => 1999, '4.35' => 435, '999999.99' => 99999999] as $usd => $cents) {
            $svc->createPaymentIntent($usd);
            Http::assertSent(fn ($r) => $r['amount'] === $cents);
        }
    }

    public function test_sub_cent_amounts_are_refused_not_rounded(): void
    {
        Http::fake();
        $this->expectException(\InvalidArgumentException::class);

        try {
            (new StripePaymentService('sk_test_x'))->createPaymentIntent('10.005');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_stripe_error_throws(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['message' => 'Card declined']], 402)]);

        $this->expectException(RuntimeException::class);
        (new StripePaymentService('sk_test_x'))->createPaymentIntent('5.00');
    }

    public function test_missing_key_throws(): void
    {
        config(['services.stripe.secret' => null]);

        $this->expectException(RuntimeException::class);
        (new StripePaymentService(null, 'w'))->createPaymentIntent('5.00');
    }

    public function test_valid_signature_passes(): void
    {
        $svc = new StripePaymentService('sk', 'whsec_a');
        $this->assertTrue($svc->verifyWebhookSignature('{"a":1}', $this->sign('{"a":1}', 'whsec_a')));
    }

    public function test_wrong_secret_fails(): void
    {
        $svc = new StripePaymentService('sk', 'whsec_a');
        $this->assertFalse($svc->verifyWebhookSignature('{"a":1}', $this->sign('{"a":1}', 'whsec_b')));
    }

    public function test_tampered_payload_fails(): void
    {
        $svc = new StripePaymentService('sk', 'whsec_a');
        $this->assertFalse($svc->verifyWebhookSignature('{"a":2}', $this->sign('{"a":1}', 'whsec_a')));
    }

    public function test_stale_timestamp_fails(): void
    {
        $svc = new StripePaymentService('sk', 'whsec_a');
        $this->assertFalse($svc->verifyWebhookSignature('{}', $this->sign('{}', 'whsec_a', time() - 3600)));
    }

    public function test_malformed_or_missing_header_fails(): void
    {
        $svc = new StripePaymentService('sk', 'whsec_a');
        $this->assertFalse($svc->verifyWebhookSignature('{}', 'garbage'));
        $this->assertFalse($svc->verifyWebhookSignature('{}', null));
    }

    public function test_missing_webhook_secret_throws(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $this->expectException(RuntimeException::class);
        (new StripePaymentService('sk', null))->verifyWebhookSignature('{}', 't=1,v1=x');
    }
}
