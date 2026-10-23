# Northfolio API (v1)

Base URL `/api/v1`. JSON in/out. Authenticated routes need `Authorization: Bearer <token>`. Every monetary value (amounts, balances, prices, totals, charge rates) is returned as a decimal **string** with 8 decimals (e.g. `"100.00000000"`); a test walks the money endpoints and fails on any JSON number. Responses use `{ "success": bool, "message": "...", "data": {...} }`. A Postman collection is in `postman/`.

## Public

| Method | Path | Notes |
|---|---|---|
| POST | `/wallet/generate` · `/wallet/challenge` · `/wallet/verify` · `/wallet/import` | Demo Wallet ID creation / sign-in |
| POST | `/login` · `/verify-otp` · `/resend-otp` · `/forgot-password` · `/verify-reset-otp` · `/reset-password` | Email accounts |
| POST | `/webhooks/stripe` | **Stripe only** — requires `Stripe-Signature` |
| POST | `/contact` · GET `/pages/{page}` `/countries` `/methods` `/submethods` | |

## Authenticated (wallet/user)

| Method | Path | Notes |
|---|---|---|
| GET | `/me`, `/dashboard`, `/assets`, `/assets/{id}/transactions` | Balances derived from the ledger |
| POST | `/register-profile`, `/profile/photo` | Onboarding |
| GET/PUT/DELETE | `/profile`, `/settings`, PUT `/password`, 2FA endpoints | |
| GET | `/transactions`, PATCH `/transactions/{id}/cancel` | Deposits cannot be cancelled by users |
| GET | `/wallet/lookup?query=` | Resolve a recipient by fingerprint/public ID → `{wallet:{id,fingerprint,public_id}}` |
| GET | `/deposits/options` | `usd_asset`, `stripe_publishable_key`, pending deposits |
| POST | `/deposits` `{usd_amount}` | → `transaction` (pending) + `client_secret` |
| GET | `/deposits/{id}` | Poll status |
| GET/POST | `/withdrawals/options`, `/withdrawals`, `/withdrawals/confirm` | 2FA confirm if enabled |
| GET/POST | `/transfers/options`, `/transfers`, `/transfers/confirm` | |
| GET/POST | `/swap/options`, `/swaps` | |
| GET/POST | `/buy/options`, `/buys` `{asset_id, usd_amount}` | Instant, from USD balance |
| GET/POST | `/sell/options`, `/sells` | Simulated payout, admin-reviewed |

## Example: deposit

```http
POST /api/v1/deposits
{ "usd_amount": 100 }

201
{ "success": true,
  "message": "Deposit initiated. Complete payment with Stripe; ...",
  "data": { "transaction": { "id": "…", "type": "deposit", "status": "pending", "amount": "100.00000000" },
            "client_secret": "pi_…_secret_…" } }
```

The balance changes only after Stripe delivers `payment_intent.succeeded` to the webhook.

## Example: buy

```http
POST /api/v1/buys
{ "asset_id": "<btc uuid>", "usd_amount": 50 }
→ "Buy order completed."  (USD −50 incl. charge, BTC credited; an internal ledger trade)
```

## Webhook responses

`200 {"received":true}` for ignored event types; `200 {"received":true,"result":"completed|cancelled|duplicate|unknown_intent|rejected:<reason>"}` for handled ones; `400` bad signature or malformed payload; `500` secret not configured.

## Errors

`422` validation / business rule (`errors` map), `401` unauthenticated, `403` forbidden, `429` rate limited, `502` payment provider failure, webhook `400` bad signature.
