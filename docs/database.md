# Northfolio Data Model

All primary keys are UUIDs.

| Table | Purpose | Key columns |
|---|---|---|
| `users` | Account owner (email/password and admin flag) | `username`, `email`, `admin`, `two_factor`, `status`, `profile_completed` |
| `profiles` | Personal details | `user_id`, names, phone, address |
| `profile_photos` | Avatar path | `user_id`, `img` |
| `wallets` | Demo wallet identity | `user_id` (nullable until onboarded), `mnemonic_hash`, `fingerprint`, `public_id` |
| `assets` | Tradable units (usd, btc, eth, usdt, trx, bnb, xrp, aapl, tsla) | `name` (unique), `label`, `decimals` (input precision; usd 2, others 8), `current_price`, `price_source(_id)`, `buy/sell/swap_charge_rate`, `active` |
| `methods` / `sub_methods` | Payout destinations for simulated withdrawals/sells | method → channels (bank, network, address…) |
| `transactions` | **The single-entry transaction ledger** (one row per leg; not double-entry) | `wallet_id`, `asset_id`, `type`, `amount decimal(24,8)`, `status`, `reference`, `external_id` (unique, nullable; provider payment id), `meta json` |
| `audit_logs` | Append-only action history (no `updated_at`, no update path in the app; not enforced by the database) | `actor_id/type`, `action`, `subject_*`, `before/after json`, `ip` |
| `registration_otps`, `password_reset_otps`, `user_tokens` | OTP and 2FA tokens | hashed codes, expiry, attempts |
| `countries` | Reference data | |
| `personal_access_tokens` | Sanctum | |

## Transaction types and `meta`

| type | legs | notable `meta` |
|---|---|---|
| `deposit` | 1 | `source=stripe`, `stripe_payment_intent_id` |
| `withdrawal` | 1 (also used for fee legs) | `swap_charge=true` on fee legs |
| `transfer` | 2 (outgoing + incoming) | `direction`, `transfer_id` |
| `swap` | 2–3 (out, in, fee) | `direction`, `swap_id`, rates; `kind=buy` when created by Buy |
| `sell` | 1 | `gross_fiat`, `charge_fiat`, `net_fiat` |

Statuses: `pending → completed | cancelled | rejected`.

Indexes: `(wallet_id, type, status)`, `(wallet_id, asset_id, type, status)`, `(type, status)`.

## Money precision

One strategy: BCMath string arithmetic at 8 dp, storage `decimal(24,8)`, API amounts serialised as 8-dp strings, input limited per asset by `assets.decimals`. Migration `2026_10_12_000100_…` widens `amount` and backfills `external_id` from `meta.stripe_payment_intent_id`; `2026_10_12_000000_…` renames `wallets.public_key` to `public_id`. Both are forward-only; older migrations were not edited. Only run on SQLite here.

## Removed from the original schema

The final migration `2026_09_23_090000_…` drops `users.referrer_id`, `wallets.mnemonic`, and the `card_requests`, `wallet_connections` and `deposit_photos` tables. Earlier migrations still create them so history replays cleanly.
