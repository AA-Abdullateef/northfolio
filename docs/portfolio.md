# Portfolio Metadata

**Title:** Northfolio — Ledger-Based Wallet & Exchange Demo
**Category:** Fintech / Backend engineering
**Role:** Backend developer (Laravel)
**Stack:** Laravel 12, PHP 8.2+, Sanctum, Blade, SQLite/MySQL, Stripe test mode, PHPUnit

**One-liner:** A Laravel ledger backend with Stripe test-mode deposits, verified webhooks and atomic multi-asset trades.

**Highlights**
* Balances derived from an auditable transaction ledger.
* Stripe PaymentIntents + HMAC-verified, idempotent webhooks (no SDK).
* Atomic swap/buy/transfer legs under row locks.
* Audit log on every money-affecting action; 117 automated tests.
* Documented security refactor: removed unsafe flows from the original codebase.

**Honest disclaimer to show on the live demo:** test mode only; no real funds; demo wallet identity.

**Screenshots:** `docs/screenshots/`
