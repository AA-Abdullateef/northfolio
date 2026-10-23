# Northfolio Engineering Decisions

## 1. Refactor: removing fraud-shaped patterns

The codebase this project started from modelled a wallet/exchange but contained flows that, taken together, matched the shape of a payment scam. They were removed or replaced rather than rebranded. This is the main engineering story of the project.

| Original pattern | Why it is a red flag | Replacement |
|---|---|---|
| Deposit = "send crypto/bank transfer, upload a screenshot, admin approves" | Unverified proof; funds credited on a human's say-so | Stripe test-mode PaymentIntents; credited **only** by a signature-verified webhook |
| Buy credited an asset with **no debit** after a proof upload | Free balance, manual approval | Buy debits USD and credits the asset atomically (swap legs, one `swap_id`) |
| Hard-coded "activate your wallet before withdrawing" for specific wallet/asset UUIDs | Classic advance-fee trap | Deleted |
| "Withdraw the full balance only" rule | Blocks partial withdrawals | Deleted |
| `blocked_withdrawal_wallets` config | Silent withdrawal blocking per account | Deleted |
| Recovery phrase stored (encrypted), shown to admins and returned by the dashboard API | Custodial exposure of a credential | Only a one-way hash is kept; column dropped |
| Admin could complete or edit any transaction, including deposits | Balance creation by operators | Admin "Complete" is blocked for deposits; edit/create views removed |
| "Connect external wallet" collecting addresses/signatures | Wallet-connect phishing shape | Removed |
| Referral tree, card requests ("credit score"), loan views | Unrelated, scam-adjacent features | Removed |
| `LoginRequest.PHP` (uppercase extension) | Login 500s on case-sensitive filesystems | Renamed |
| Real brand names, keys and bank details in seeds/docs | Impersonation + leaked config | Rebranded to "Northfolio"; `.env` not shipped |

The wallet "identity" is kept as a genuine BIP-39 phrase used as a demo login secret, relabelled **Demo Wallet ID** with disclaimers in the UI and API messages (see [security.md](security.md)).

## 2. Ledger over stored balances

The ledger is single-entry: one `transactions` row per leg, with linked legs sharing an id. It is not double-entry bookkeeping.

Balances are a pure function of transaction rows, so there is no balance column to drift. Cost: each read sums rows (indexed). Benefit: any balance is auditable from history, and legs make multi-asset operations explainable.

## 3. Debits count while pending, credits only when completed

A pending withdrawal/transfer/swap immediately reduces spendable balance, which prevents double-spend while an operation is in flight; incoming value appears only on completion.

## 4. Atomic multi-leg completion

`TransactionLifecycleService::complete/cancel` locks the row, then moves the transaction and every linked leg inside one `DB::transaction`. Cancel works the same way, which also releases fee legs. See [transaction-lifecycle.md](transaction-lifecycle.md).

## 5. Stripe without the SDK

Only two Stripe calls are needed (create PaymentIntent, verify webhook), so a ~100-line service over Laravel's HTTP client keeps the dependency surface small. Signature verification follows Stripe's scheme: `HMAC-SHA256(secret, "{t}.{payload}")`, `hash_equals`, 300-second tolerance, multiple `v1` signatures supported.

## 6. Idempotent, authenticated webhooks

The endpoint is public but useless without a valid signature (400 otherwise, 500 if the secret is unset). Handling keys off the unique `external_id` and the `pending` state, and checks status, currency, amount and metadata,, so retries and duplicate events cannot credit twice. Users cannot cancel deposits, avoiding a paid-but-cancelled race.

## 7. Money-handling precision

BCMath strings at 8 dp end to end (`Money`), `decimal(24,8)` storage, per-asset input precision (`assets.decimals`), half-away-from-zero rounding, Stripe amounts as integer cents. Float math was removed from balance and aggregation code; Stripe cents use `Money::toMinorUnits` (exact, rejects sub-cent input). Remaining float exposure: JSON numbers parsed at the request boundary, and SQLite itself storing decimals as doubles (dev/test only; MySQL/Postgres keep DECIMAL exactly). Previously `decimal(15,5)` storage silently truncated 8-dp quantities.

## 8. Known limitations (stated, not hidden)

* Row locks are verified by simulation on SQLite only; MySQL/Postgres behaviour was not run.
* Withdrawals/sells are admin-reviewed simulations; there is no payout provider.
* Price sync depends on third-party APIs and free-tier limits.
* The web UI is Blade + vanilla JS calling the API; there is no SPA build step.
