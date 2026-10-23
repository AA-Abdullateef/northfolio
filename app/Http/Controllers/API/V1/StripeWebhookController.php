<?php

namespace App\Http\Controllers\API\V1;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Services\NotificationService;
use App\Services\StripePaymentService;
use App\Services\TransactionLifecycleService as Lifecycle;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY code path that completes a deposit.
 *
 * Trust chain: valid Stripe signature (HMAC, 5-minute window) → event type →
 * deposit located by its UNIQUE provider id (transactions.external_id) →
 * under a row lock: still pending, PaymentIntent status/currency/amount match
 * the deposit, metadata points back at this deposit → lifecycle complete().
 * Duplicate or concurrent deliveries find a non-pending row and become no-ops.
 */
class StripeWebhookController extends Controller
{
    private const COMPLETE_EVENT = 'payment_intent.succeeded';
    private const CANCEL_EVENTS  = ['payment_intent.payment_failed', 'payment_intent.canceled'];

    public function __construct(
        private readonly StripePaymentService $stripe,
        private readonly Lifecycle $lifecycle,
        private readonly NotificationService $notifications,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->getContent();

        try {
            $valid = $this->stripe->verifyWebhookSignature($payload, $request->header('Stripe-Signature'));
        } catch (\RuntimeException) {
            return response()->json(['message' => 'Webhook not configured.'], 500);
        }

        if (! $valid) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $event = json_decode($payload, true);

        if (! is_array($event) || ! is_string($event['type'] ?? null) || ! is_array($event['data']['object'] ?? null)) {
            return response()->json(['message' => 'Malformed event payload.'], 400);
        }

        $type   = $event['type'];
        $intent = $event['data']['object'];

        if ($type !== self::COMPLETE_EVENT && ! in_array($type, self::CANCEL_EVENTS, true)) {
            return response()->json(['received' => true]);
        }

        if (! is_string($intent['id'] ?? null) || $intent['id'] === '') {
            return response()->json(['message' => 'Malformed payment intent.'], 400);
        }

        $result = DB::transaction(function () use ($type, $intent) {
            $tx = Transaction::where('external_id', $intent['id'])
                ->where('type', TransactionType::Deposit->value)
                ->lockForUpdate()
                ->first();

            if (! $tx) {
                return 'unknown_intent';
            }

            if (! $tx->isPending()) {
                return 'duplicate';
            }

            if ($type === self::COMPLETE_EVENT) {
                if ($reason = $this->mismatch($tx, $intent)) {
                    AuditLog::record(
                        action: 'deposit.webhook_rejected',
                        actorType: Lifecycle::ACTOR_SYSTEM,
                        subjectType: 'transaction',
                        subjectId: $tx->id,
                        after: ['reason' => $reason, 'payment_intent' => $intent['id']],
                    );

                    return 'rejected:' . $reason;
                }

                $this->lifecycle->complete($tx, Lifecycle::ACTOR_SYSTEM, null, notify: false);

                return 'completed';
            }

            $this->lifecycle->cancel($tx, Lifecycle::ACTOR_SYSTEM, null, notify: false);

            return 'cancelled';
        });

        // Notify after commit (a failing mail must never roll back a credited deposit).
        if (in_array($result, ['completed', 'cancelled'], true)) {
            $tx = Transaction::where('external_id', $intent['id'])->with('wallet.user')->first();
            if ($tx?->wallet) {
                $this->notifications->sendTransactionNotice($tx->wallet, $tx);
            }
        }

        return response()->json(['received' => true, 'result' => $result]);
    }

    /** Returns a reason string if the provider payment does not match the deposit. */
    private function mismatch(Transaction $tx, array $intent): ?string
    {
        if (($intent['status'] ?? null) !== 'succeeded') {
            return 'status_not_succeeded';
        }

        if (strtolower((string) ($intent['currency'] ?? '')) !== 'usd') {
            return 'currency_mismatch';
        }

        $received = $intent['amount_received'] ?? $intent['amount'] ?? null;
        try {
            $expected = (string) Money::toMinorUnits(Money::of($tx->amount), 2);
        } catch (\InvalidArgumentException) {
            return 'amount_mismatch';
        }

        if (! is_int($received) || (string) $received !== $expected) {
            return 'amount_mismatch';
        }

        $metaTx = $intent['metadata']['transaction_id'] ?? null;

        if ($metaTx !== null && $metaTx !== $tx->id) {
            return 'metadata_mismatch';
        }

        return null;
    }
}
