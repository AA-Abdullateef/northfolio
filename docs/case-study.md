# Building Northfolio: Turning a Risky Wallet App into a Portfolio-Grade Ledger Demo

## The brief
I inherited a Laravel codebase for a wallet/exchange app and wanted it as a public portfolio project. A careful audit showed that, while the ledger core was decent, the product flows around it were not something I could responsibly publish.

## What I found
* Deposits were credited by an admin after a screenshot "proof".
* "Buy" credited assets without debiting anything.
* Withdrawals had a hard-coded "activate your wallet" block and a full-balance-only rule.
* Recovery phrases were stored and shown to admins.
* Referrals, card "credit score" requests and wallet-connect forms rounded out the pattern.

## What I did
1. **Analysed** each flow for how it could be abused and removed the ones with no honest use.
2. **Replaced** deposits with Stripe test-mode PaymentIntents completed only by a signed, idempotent webhook.
3. **Re-modelled Buy** as an atomic USD→asset trade using the existing swap-leg machinery.
4. **Stopped storing** recovery phrases; kept a hash for sign-in; labelled the identity "Demo Wallet ID".
5. **Locked down admin powers** so operators cannot create balance.
6. **Tested** the new paths (117 tests, including forged-signature, duplicate-delivery and stale-timestamp cases).
7. **Rebranded**, sanitised config/seeds, rewrote the docs, shipped ledger-consistent demo data.

## Outcome
A compact, honest example of money-handling engineering: derived balances, atomic multi-leg operations, webhook security, audit logging — and a clear record of the judgement calls behind them.

## What I'd do next
Queue-based webhook handling, a real payout provider in test mode, Postgres row-level locking tests, a minimal SPA front end, and CI.
