<?php

use App\Http\Controllers\API\V1\AccountController;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\StripeWebhookController;
use App\Http\Controllers\API\V1\ExchangeController;
use App\Http\Controllers\API\V1\FundsController;
use App\Http\Controllers\API\V1\ProfileController;
use App\Http\Controllers\API\V1\PublicController;
use App\Http\Controllers\API\V1\ReferenceController;
use App\Http\Controllers\API\V1\TransactionController;
use App\Http\Controllers\API\V1\WalletSetupController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ── Public: Stripe webhook (signature-verified, no auth) ────────────────
    Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle']);

    // ── Public: Auth ─────────────────────────────────────────────────────────
    Route::middleware('throttle:auth')->group(function () {
        Route::post('login',                   [AuthController::class, 'login']);
        Route::post('verify-otp',              [AuthController::class, 'verifyRegistrationOtp']);
        Route::post('resend-otp',              [AuthController::class, 'resendRegistrationOtp']);
        Route::post('forgot-password',         [AuthController::class, 'forgotPassword']);
        Route::post('verify-reset-otp',        [AuthController::class, 'verifyResetOtp']);
        Route::post('reset-password',          [AuthController::class, 'resetPassword']);
    });

    // ── Public: Wallet Setup ─────────────────────────────────────────────────
    Route::middleware('throttle:wallet')->prefix('wallet')->group(function () {
        Route::post('generate',   [WalletSetupController::class, 'generate']);
        Route::post('challenge',  [WalletSetupController::class, 'challenge']);
        Route::post('verify',     [WalletSetupController::class, 'verify']);
        Route::post('import',     [WalletSetupController::class, 'import']);
    });

    // ── Public: Contact & Pages ──────────────────────────────────────────────
    Route::middleware('throttle:api')->group(function () {
        Route::post('contact',        [PublicController::class, 'contact']);
        Route::get('pages/{page}',    [PublicController::class, 'page']);
        Route::get('countries',       [ReferenceController::class, 'countries']);
        Route::get('methods',         [ReferenceController::class, 'methods']);
        Route::get('submethods',      [ReferenceController::class, 'subMethods']);
    });

    // ── Authenticated ────────────────────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

        // Auth
        Route::post('/wallet/complete-profile', [AuthController::class, 'completeProfile']);
        Route::post('register-profile', [AuthController::class, 'completeProfile']);
        Route::get('me',      [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);

        // Onboarding
        Route::post('profile/photo', [ProfileController::class, 'uploadPhoto']);

        Route::middleware('onboarded')->group(function () {

            Route::get('wallet/lookup',      [WalletSetupController::class, 'lookup']);

            // Dashboard & Asset Overview
            Route::get('dashboard',                         [AccountController::class, 'dashboard']);
            Route::get('assets',                            [AccountController::class, 'assetsOverview']);
            Route::get('assets/{assetId}/transactions',     [AccountController::class, 'assetTransactions']);

            // Pre-flight options
            Route::get('deposits/options',    [AccountController::class, 'depositOptions']);
            Route::get('withdrawals/options', [AccountController::class, 'withdrawalOptions']);
            Route::get('transfers/options',   [AccountController::class, 'transferOptions']);

            // Profile
            Route::get('profile',                            [ProfileController::class, 'show']);
            Route::put('profile',                            [ProfileController::class, 'update']);
            Route::delete('profile',                         [ProfileController::class, 'deactivate']);
            Route::get('settings',                           [ProfileController::class, 'settings']);
            Route::put('password',                           [ProfileController::class, 'updatePassword']);
            Route::post('two-factor/enable',                 [ProfileController::class, 'enableTwoFactor']);
            Route::post('two-factor/disable',                [ProfileController::class, 'disableTwoFactor']);
            Route::post('two-factor/confirm',                [ProfileController::class, 'confirmTwoFactorDeactivation']);

            // Transactions
            Route::get('transactions',                           [TransactionController::class, 'index']);
            Route::patch('transactions/{transaction}/cancel',    [TransactionController::class, 'updateStatus']);

            // Deposits
            Route::post('deposits',                    [FundsController::class, 'initiateDeposit']);
            Route::get('deposits/{transaction}',       [FundsController::class, 'showDeposit']);

            // Withdrawals
            Route::post('withdrawals',         [FundsController::class, 'initiateWithdrawal']);
            Route::post('withdrawals/confirm', [FundsController::class, 'confirmWithdrawal']);

            // Transfers
            Route::post('transfers',         [FundsController::class, 'initiateTransfer']);
            Route::post('transfers/confirm', [FundsController::class, 'confirmTransfer']);

            // ── Exchange: Swap, Buy, Sell ─────────────────────────────────────
            Route::get('swap/options',  [ExchangeController::class, 'swapOptions']);
            Route::post('swaps',        [ExchangeController::class, 'initiateSwap']);

            Route::get('buy/options',   [ExchangeController::class, 'buyOptions']);
            Route::post('buys',         [ExchangeController::class, 'initiateBuy']);

            Route::get('sell/options',  [ExchangeController::class, 'sellOptions']);
            Route::post('sells',        [ExchangeController::class, 'initiateSell']);

        });
    });
});
