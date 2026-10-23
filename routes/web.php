<?php

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

Route::prefix('wallet-setup')->group(function () {
    Route::view('generate',  'user.setup.generate')->name('wallet.view.generate');
    Route::view('challenge', 'user.setup.challenge')->name('wallet.view.challenge');
    Route::view('verify',    'user.setup.verify')->name('wallet.view.verify');
    Route::view('import',    'user.setup.import')->name('wallet.view.import');
});


Route::view('/dashboard', 'user.dashboard')->name('dashboard');
Route::view('/transactions', 'user.transactions')->name('user.transactions.index');
Route::view('/assets', 'user.assets')->name('user.assets.index');
Route::view('/deposits', 'user.deposits')->name('user.deposits.index');
Route::view('/withdrawals', 'user.withdrawals')->name('user.withdrawals.index');
Route::view('/transfers', 'user.transfers')->name('user.transfers.index');
Route::view('/profile', 'user.profile')->name('user.profile.index');
Route::view('/wallets', 'user.wallets')->name('user.wallets.index');
Route::view('/swap', 'user.swap')->name('user.swap.index');
Route::view('/buy', 'user.buy')->name('user.buy.index');
Route::view('/sell', 'user.sell')->name('user.sell.index');

// ── User Auth (inactive — wallet is the only user entry point) ───────────────
// Blade files remain in resources/views/user/auth/ for reference or future use.
// Route::view('/login',           'user.auth.login')->name('user.login');
// Route::view('/register',        'user.auth.register')->name('user.register');
// Route::view('/forgot-password', 'user.auth.forgot_password')->name('user.forgot-password');

// Root and fallback login — both go to wallet setup since user auth is wallet-only.
Route::get('/', fn () => redirect()->route('admin.login'));
Route::get('/login', fn () => redirect()->route('wallet.view.generate'))->name('login');

// ── Admin Auth ────────────────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest:admin')->group(function () {
        Route::get('login',  [Admin\AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'login'])->name('login.submit');
    });

    Route::post('logout', [Admin\AuthController::class, 'logout'])
        ->middleware('auth:admin')
        ->name('logout');

    // ── Admin Portal ─────────────────────────────────────────────────────────
    Route::middleware(['auth:admin', 'admin'])->group(function () {

        // Dashboard
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        // Users
        Route::get('users',                         [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('users/create',                  [Admin\UserController::class, 'create'])->name('users.create');
        Route::post('users',                        [Admin\UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}',                  [Admin\UserController::class, 'show'])->name('users.show');
        Route::get('users/{user}/edit',             [Admin\UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}',                  [Admin\UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}',               [Admin\UserController::class, 'destroy'])->name('users.destroy');
        Route::post('users/{user}/reset-password',  [Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('users/{user}/toggle-2fa',      [Admin\UserController::class, 'toggleTwoFactor'])->name('users.toggle-2fa');
        Route::post('users/{user}/verify-email',    [Admin\UserController::class, 'verifyEmail'])->name('users.verify-email');
        Route::get('users/{user}/assets/{asset}',   [Admin\UserController::class, 'assetDetails'])->name('users.asset-details');

        // Transactions
        Route::get('transactions',                         [Admin\TransactionController::class, 'index'])->name('transactions.index');
        Route::get('transactions/{transaction}/show', [Admin\TransactionController::class, 'show'])->name('transactions.show');
        Route::post('transactions/{transaction}/complete', [Admin\TransactionController::class, 'complete'])->name('transactions.complete');
        Route::post('transactions/{transaction}/cancel', [Admin\TransactionController::class, 'cancel'])->name('transactions.cancel');

        // Assets
        Route::get('assets',                 [Admin\AssetController::class, 'index'])->name('assets.index');
        // The "Sync Prices" route that triggers the price sync 
        Route::post('assets/sync-prices',    [Admin\AssetController::class, 'syncPrices'])->name('assets.sync-prices');
        Route::get('assets/create',          [Admin\AssetController::class, 'create'])->name('assets.create');
        Route::post('assets',                [Admin\AssetController::class, 'store'])->name('assets.store');
        Route::get('assets/{asset}/edit',    [Admin\AssetController::class, 'edit'])->name('assets.edit');
        Route::put('assets/{asset}',         [Admin\AssetController::class, 'update'])->name('assets.update');
        Route::delete('assets/{asset}',      [Admin\AssetController::class, 'destroy'])->name('assets.destroy');

        // Payment Methods
        Route::get('methods',                [Admin\MethodController::class, 'index'])->name('methods.index');
        Route::get('methods/create',         [Admin\MethodController::class, 'create'])->name('methods.create');
        Route::post('methods',               [Admin\MethodController::class, 'store'])->name('methods.store');
        Route::get('methods/{method}/edit',  [Admin\MethodController::class, 'edit'])->name('methods.edit');
        Route::put('methods/{method}',       [Admin\MethodController::class, 'update'])->name('methods.update');
        Route::delete('methods/{method}',    [Admin\MethodController::class, 'destroy'])->name('methods.destroy');

        // Sub-Methods (nested under method)
        Route::get('methods/{method}/sub-methods/create',              [Admin\MethodController::class, 'createSubMethod'])->name('methods.sub-methods.create');
        Route::post('methods/{method}/sub-methods',                    [Admin\MethodController::class, 'storeSubMethod'])->name('methods.sub-methods.store');
        Route::get('methods/{method}/sub-methods/{subMethod}/edit',    [Admin\MethodController::class, 'editSubMethod'])->name('methods.sub-methods.edit');
        Route::put('methods/{method}/sub-methods/{subMethod}',         [Admin\MethodController::class, 'updateSubMethod'])->name('methods.sub-methods.update');
        Route::delete('methods/{method}/sub-methods/{subMethod}',      [Admin\MethodController::class, 'destroySubMethod'])->name('methods.sub-methods.destroy');

        // Wallets (mnemonic accounts)
        Route::get('wallets',                       [Admin\WalletController::class, 'index'])->name('wallets.index');
        Route::get('wallets/{wallet}',              [Admin\WalletController::class, 'show'])->name('wallets.show');
        Route::delete('wallets/{wallet}',           [Admin\WalletController::class, 'destroy'])->name('wallets.destroy');

        // Mailer
        Route::get('mailer',        [Admin\MailerController::class, 'index'])->name('mailer.index');
        Route::post('mailer/send',  [Admin\MailerController::class, 'send'])->name('mailer.send');

        // Tokens
        Route::get('tokens',            [Admin\TokenController::class, 'index'])->name('tokens.index');
        Route::delete('tokens/{token}', [Admin\TokenController::class, 'destroy'])->name('tokens.destroy');
    });

    // ── Automated price sync ─────────────────────────────────────────────────
    // Deliberately outside the auth:admin group — meant to be hit by an
    // external cron/uptime service (e.g. cron-job.org, a server crontab curl)
    // on a schedule, which can't carry an interactive admin session. Guarded
    // by a shared secret instead. The admin panel's own "Sync Prices" button
    // (assets.sync-prices, POST, inside the authenticated group above) is the
    // manual/interactive trigger and is unaffected by this.
    Route::get('assets/sync-prices/run', function (\App\Services\AssetPriceService $priceService, \Illuminate\Http\Request $request) {
        $expected = (string) config('services.price_sync.key');

        if ($expected === '' || ! hash_equals($expected, (string) $request->query('key'))) {
            abort(403, 'Invalid or missing sync key.');
        }

        return response()->json($priceService->syncAll());
    })->name('assets.sync-prices.run');
});