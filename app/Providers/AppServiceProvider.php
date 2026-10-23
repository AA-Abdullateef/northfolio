<?php

namespace App\Providers;

use App\Events\DepositInitiated;
use App\Events\TransactionCancelled;
use App\Events\TransactionCompleted;
use App\Events\TransferInitiated;
use App\Events\UserRegistered;
use App\Events\WithdrawalInitiated;
use App\Listeners\SendDepositNotification;
use App\Listeners\SendRegistrationNotifications;
use App\Listeners\SendTransactionCompletedNotification;
use App\Listeners\SendTransactionCancelledNotification;
use App\Listeners\SendWithdrawalNotification;
use App\Listeners\SendTransferNotification;
use App\Services\FundsService;
use App\Services\IdentityService;
use App\Services\LedgerService;
use App\Services\NotificationService;
use App\Services\OtpService;
use App\Services\StripePaymentService;
use App\Services\TransactionLifecycleService;
use App\Services\WalletService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OtpService::class);
        $this->app->singleton(LedgerService::class);
        $this->app->singleton(NotificationService::class);

        $this->app->singleton(IdentityService::class, function ($app) {
            return new IdentityService($app->make(OtpService::class));
        });

        $this->app->singleton(WalletService::class);

        $this->app->singleton(FundsService::class, function ($app) {
            return new FundsService(
                $app->make(LedgerService::class),
                $app->make(StripePaymentService::class),
                $app->make(TransactionLifecycleService::class),
            );
        });
    }

    public function boot(): void
    {
        // Admin tables use the console pager rather than the default Tailwind view.
        Paginator::defaultView('pagination.console');

        Request::macro('wallet', function () {
            $identity = $this->user();

            if ($identity instanceof \App\Models\Wallet) {
                return $identity;
            }

            return $identity?->wallet;
        });

        $this->registerEvents();
        $this->registerRateLimiters();
    }

    private function registerEvents(): void
    {
        Event::listen(UserRegistered::class,      SendRegistrationNotifications::class);
        Event::listen(DepositInitiated::class,    SendDepositNotification::class);
        Event::listen(WithdrawalInitiated::class, SendWithdrawalNotification::class);
        Event::listen(TransferInitiated::class,   SendTransferNotification::class);
        Event::listen(TransactionCompleted::class, SendTransactionCompletedNotification::class);
        Event::listen(TransactionCancelled::class, SendTransactionCancelledNotification::class);
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('wallet', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(60)->by($request->user()->id)
                : Limit::perMinute(30)->by($request->ip());
        });
    }
}
