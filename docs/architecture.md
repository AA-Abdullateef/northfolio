# Northfolio Architecture

## Layers

```
HTTP (routes/api.php, routes/web.php)
  └─ Form Requests (validation)      app/Http/Requests/API/V1
  └─ Controllers (thin)              app/Http/Controllers/{API/V1,Admin}
       └─ Services (business rules)  app/Services
            ├─ LedgerService    balances (BCMath), guards, valuations
            ├─ TransactionLifecycleService  the only place status changes
            ├─ Support\Money / Support\Bip39  exact decimals, mnemonic encoding
            ├─ FundsService     deposit / withdraw / transfer / swap / buy / sell
            ├─ StripePaymentService  PaymentIntents + webhook signature check
            ├─ WalletService / IdentityService  wallet-first auth, onboarding
            ├─ AssetPriceService  CoinGecko / AlphaVantage / Finnhub sync
            ├─ NotificationService / OtpService  mail + OTP/2FA
            └─ AuditService      audit rows (append-only by convention)
       └─ Models + Enums           app/Models, app/Enums (TransactionType/Status)
```

Controllers validate, call one service method, and shape the response with API Resources. Rules live in services so they are testable without HTTP.

## Authentication

* **Wallet-first:** a genuine BIP-39 12-word phrase is used as a demo login secret; the server stores only `mnemonic_hash`, `fingerprint`, `public_id`. The phrase is shown once at creation and never persisted. It is not a blockchain wallet. See `API_WALLET_FIRST.md`.
* **Sanctum** bearer tokens for the API; the `onboarded` middleware gates money endpoints until a profile is completed.
* **Admin panel** uses a separate guard and `AdminAccess` middleware.
* Rate limiters: `auth` 10/min/IP, `wallet` 20/min/IP, `api` 60/min/user.

## The ledger

The `transactions` table is a **single-entry transaction ledger**: each row is one signed-by-type leg against one wallet and one asset. It is *not* double-entry bookkeeping (no chart of accounts, no balancing counter-entry, no trial balance). Related legs are linked by `meta.swap_id` / `meta.transfer_id`; the platform fee is its own leg. Balances are never stored; they are computed from rows.

A wallet's balance for an asset is computed from `transactions`:

```
balance = completed deposits + incoming transfers + incoming swaps
        − (pending|completed) withdrawals − outgoing transfers − outgoing swaps − sells
```

Debits count while **pending** (funds are locked); credits count only when **completed**. Multi-leg operations (transfer, swap, buy) create legs that share `meta.transfer_id` / `meta.swap_id`; `TransactionLifecycleService` completes or cancels all legs inside one DB transaction with row locks, so a half-applied swap cannot exist. See [transaction-lifecycle.md](transaction-lifecycle.md).

## Deposit sequence

1. `POST /deposits {usd_amount}` → `FundsService::initiateDeposit` creates a Pending deposit, calls Stripe, stores the PaymentIntent id in `meta`.
2. The browser confirms the PaymentIntent with Stripe.js (test card).
3. Stripe calls `POST /api/v1/webhooks/stripe`. `StripeWebhookController` verifies the signature, finds the deposit by unique `external_id`, checks status/currency/amount/metadata, and completes it via the lifecycle service. Duplicate deliveries are no-ops.

## Buy sequence

`FundsService::initiateBuy` locks the wallet row, checks price + USD balance, creates outgoing-USD, incoming-asset and charge legs (one `swap_id`), then completes them atomically. The legs therefore debit USD for exactly the value they credit, plus the fee leg.

## Admin panel

Dashboard, users, wallets (identifiers only), transactions (complete/cancel non-deposit rows through the lifecycle service), assets (pricing + charge rates + manual sync), payout methods, mailer, tokens. Every state change writes an `audit_logs` row.
