# Security Notes

Northfolio is a portfolio demo (Stripe **test mode**, internal ledger). This page states what is protected, how, and what is *not* claimed. It is not a security certification, a penetration-test result or a compliance statement.

## Wallet identity (demo, not a blockchain wallet)

* The 12 words are a **genuine BIP-39 mnemonic**: 128 bits of entropy from a CSPRNG, SHA-256 checksum, 11-bit indexes into the official English wordlist (`app/Support/Bip39.php`, `resources/wordlists/bip39_english.txt`). Unit tests check the official test vectors, checksum rejection and wordlist integrity. Imports are validated with the checksum.
* It is still a **Demo Wallet ID**: no keys are derived, nothing is on-chain, and no real assets are controlled. UI and API messages say so.
* Only derived values are stored: `mnemonic_hash` (one-way), `fingerprint` and `public_id` (both HMAC with `APP_KEY`). The phrase is never stored or logged. It is returned once by `POST /wallet/generate` and held server-side (cache) under a short-lived single-use `setup_token` until verification.
* Onboarding keeps a 3-word challenge: distinct random positions, token expires after 20 minutes, 5 wrong attempts burn the token, verify is refused before a challenge is issued.
* Rotating `APP_KEY` changes fingerprints/public IDs; the legacy `mnemonic_hash` fallback still lets import work.

## Money integrity

* Amounts are BCMath strings at 8 dp (`App\Support\Money`), stored as `decimal(24,8)`; input precision per asset comes from `assets.decimals` (USD 2). No float arithmetic touches balances: the ledger reads `amount` as text in SQL, Stripe cents are derived by `Money::toMinorUnits` (BCMath, refuses sub-cent amounts), and the API returns strings. Floats exist only where PHP parses a JSON number at the request boundary and (SQLite only) inside SQLite's own storage. `ext-bcmath` is required.
* Status changes are centralised and locked ([transaction-lifecycle.md](transaction-lifecycle.md)).
* Deposits credit only from a verified webhook; unique `external_id` prevents one payment crediting twice.

## Stripe webhook

Rejects without a valid `Stripe-Signature` (HMAC-SHA256 of `"{t}.{payload}"`, `hash_equals`, 300 s tolerance, 400); 500 if the secret is unset; 400 for malformed JSON; ignores unrelated event types; unknown intents and duplicates are no-ops; status, currency, amount (integer cents) and metadata must match the deposit. Tested with locally signed payloads only; the suite never calls Stripe.

## Authentication and authorization

* Sanctum bearer tokens; wallet tokens cannot use money endpoints until onboarding is complete (`onboarded` middleware).
* Transaction cancel, deposit polling and transfers are ownership-checked (403 for other wallets' rows).
* Admin panel: separate guard + `AdminAccess`; signed-in non-admins get 404, guests are redirected to the login page. Admin cannot complete deposits.
* OTPs (registration, password reset, 2FA) are stored hashed, expire after 10 minutes and allow 5 attempts; a password-reset OTP cannot be replayed after use, and the reset token lasts 15 minutes.
* Rate limits: `auth` 10/min/IP, `wallet` 20/min/IP, `api` 60/min/user (30/min/IP anonymous).
* Every money-affecting action writes an `audit_logs` row. The table has no update path in the application, but nothing at the database level prevents modification.

## Known limitations (not hidden)

* Concurrency control relies on row locks that SQLite ignores; true parallel behaviour was not exercised on MySQL/Postgres in this project.
* The decimal migration was run on SQLite only; run it on a copy of MySQL data before relying on it.
* No KYC/AML, no real payout rail, no external security audit, no dependency vulnerability scan was run, and mail/price providers were not exercised live.
* Not production-ready and makes no regulatory-compliance claim.
