# Transaction Lifecycle

Every status change in Northfolio goes through one class: `App\Services\TransactionLifecycleService` (`complete()` / `cancel()`). Admin actions, user cancellation, the Stripe webhook and instant trades (Buy) all call it; no controller or model sets `status` directly.

## State machine

```
pending ──complete()──▶ completed   (terminal)
pending ──cancel()────▶ cancelled   (terminal)
```

`completed` and `cancelled` can never be processed again; a second attempt raises `InvalidTransactionTransition` (HTTP 422 on the API, a flash error in the admin panel). The `rejected` status value exists in the enum but nothing currently moves a row into it.

## Who may do what

| Actor | complete | cancel |
|---|---|---|
| `system` (verified Stripe webhook) | deposits | deposits |
| `admin` (Blade panel) | non-deposit rows | non-deposit rows |
| `user` (API) | never | own non-deposit rows |

Deposits are provider-backed, so neither admins nor users can complete or cancel them. Ownership is checked by the controller (`wallet_id` must match the caller; otherwise 403).

## Linked legs

Multi-leg operations share an id in `meta`: `transfer_id` (out + in) or `swap_id` (out + in + optional fee leg; Buy is a swap with `meta.kind = "buy"`). A transition:

1. opens one DB transaction;
2. re-reads the target row with `lockForUpdate()` (never trusts the caller's model);
3. rejects non-pending targets;
4. loads and locks the whole group; if any leg is not pending, the whole operation is refused;
5. checks group shape (a transfer has exactly two legs; a swap has an outgoing and an incoming leg);
6. updates every leg and writes one `audit_logs` row, all-or-nothing.

Legacy transfers created before `transfer_id` existed are paired by a heuristic.

## Reserves and fees

Balances are derived from the ledger. Debits count while `pending` and credits count only when `completed` (see [architecture](architecture.md)). So:

* a pending withdrawal / outgoing transfer / swap **reserves** funds immediately;
* **cancelling** moves every leg, including the fee leg, to `cancelled`, so reserve and fee stop counting and the balance returns exactly to its pre-operation value (tested);
* **completing** turns the incoming credit on and keeps the debits and fee.

## Concurrency

On MySQL/Postgres the row locks serialise concurrent complete/cancel calls; the loser sees a non-pending row and is refused. SQLite ignores `FOR UPDATE`, so the test suite simulates the race with a stale in-memory model (the service re-reads and refuses) rather than true parallel requests. The DB-level backstop is the unique `transactions.external_id`, which ensures one provider payment maps to at most one ledger row.

## Deposit path

1. `POST /deposits` creates a `pending` deposit and a Stripe test-mode PaymentIntent; the PaymentIntent id is stored in `external_id`.
2. Stripe calls `POST /webhooks/stripe`. After signature, parsing, lookup, status, currency, amount and metadata checks, the webhook calls `complete()` as `system`. Failed/cancelled intents call `cancel()`.
3. A mismatching `payment_intent.succeeded` leaves the deposit pending, writes a `deposit.webhook_rejected` audit row and returns `200 {"received":true,"result":"rejected:<reason>"}` so Stripe does not retry forever; an operator can investigate.

Webhook `result` values: `completed`, `cancelled`, `duplicate`, `unknown_intent`, `rejected:status_not_succeeded | currency_mismatch | amount_mismatch | metadata_mismatch`.

## Instant trades

Buy creates its legs and completes them inside the same request through the same service path, so a Buy never leaves a half-applied state. Swaps, sells, withdrawals and transfers are created `pending` and finished by an admin (withdrawals/sells are simulated payouts).

## Tests

`tests/Feature/TransactionLifecycleTest.php`, `StripeWebhookTest.php`, `FundsBehaviorTest.php` and `AuthorizationTest.php` cover every rule above.
