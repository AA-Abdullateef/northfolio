# Northfolio Wallet-First Authentication Design

> **Note:** this design document has been updated to match the code. Migration `2026_09_23_090000_…` drops `wallets.mnemonic`, so only `mnemonic_hash`, `fingerprint` and `public_id` (renamed from `public_key` by `2026_10_12_000000_…`) are stored. Wallet-connection routes mentioned in older drafts were removed.

## Architecture

Wallet identity is the primary authentication provider. A wallet identity is stored in `wallets` with:

- `mnemonic_hash`
- `fingerprint`
- `public_id`
- nullable `user_id`

Plaintext mnemonics are never stored. The mnemonic (genuine BIP-39, checksum-validated; a demo identity, not a blockchain wallet) is shown only during generation, cached temporarily under a single-use setup token (20 min, 5 failed verifications burn it), and then discarded.

`users` and `profiles` are the account layer. They are created only after the wallet holder completes profile registration. Transactions belong to a wallet (`transactions.wallet_id`), not directly to a user.

## Public / Onboarding Endpoints

- `POST /api/v1/wallet/generate`
- `POST /api/v1/wallet/challenge`
- `POST /api/v1/wallet/verify`
- `POST /api/v1/wallet/import`
- `GET /api/v1/countries`
- `GET /api/v1/methods`
- `GET /api/v1/submethods`
- `POST /api/v1/login` - backup only
- `POST /api/v1/forgot-password`
- `POST /api/v1/verify-reset-otp`
- `POST /api/v1/reset-password`

`POST /api/v1/register-profile` requires a wallet Sanctum token. It creates the user/profile account layer, attaches the wallet, sends the email OTP, revokes the wallet token, and returns a user token.

## Protected User Endpoints

- `GET /api/v1/me`
- `POST /api/v1/profile/photo`
- all dashboard, asset, deposit, withdrawal, transfer, transaction, password, settings, and 2FA routes

Financial routes use `EnsureOnboardingCompleted`. The middleware blocks access when:

- the token is a wallet token without an attached user
- `profile_completed` is false
- `email_verified_at` is null

## Removed Legacy API Surface

- `POST /api/v1/register`
- `POST /api/v1/admin/transactions/complete`
- `POST /api/v1/admin/transactions/update-status`

Admin transaction actions remain in the Blade admin surface.

## Migration Path

The wallet-first model was built into the base schema, then tightened by later migrations (in `database/migrations`, in order):

1. `0001_01_01_000000_create_users_table`: `username`, `email` and `password` are nullable and `profile_completed` defaults to false, so a user row is created only after onboarding.
2. `2026_06_12_000000_create_wallets_table`: wallets with `mnemonic_hash`, `fingerprint`, `public_key` and a nullable `user_id`.
3. `2026_06_26_154746_add_mnemonic_to_wallets_table`: added a plaintext-capable `mnemonic` column (a later-removed mistake).
4. `2026_09_23_090000_remove_referrals_card_requests_wallet_connections`: drops `wallets.mnemonic` (not restored on rollback), plus referrals, card requests, wallet connections and deposit photos.
5. `2026_10_12_000000_rename_wallet_public_key_to_public_id`: renames `public_key` to `public_id`, since it is an HMAC-derived identifier and not a cryptographic public key.

There is no data backfill from an encrypted mnemonic column: the earlier draft described `2026_06_20_…`/`2026_06_21_…` migrations that were never part of this repository. Seeded demo wallets use deterministic BIP-39 phrases that are public by design; real data should have one phrase per wallet identity.
