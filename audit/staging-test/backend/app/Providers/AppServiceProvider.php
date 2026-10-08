<?php

namespace App\Providers;

use App\Contracts\WhatsAppProvider;
use App\Events\OutcomeRecordedEvent;
use App\Listeners\UpdateDriverMetricsListener;
use App\Models\Driver;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecipient;
use App\Services\DashboardService;
use App\WhatsApp\CloudApiProvider;
use App\WhatsApp\FakeWhatsAppProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WhatsAppProvider::class, function () {
            return match (config('whatsapp.provider', 'fake')) {
                'cloud' => new CloudApiProvider,
                default => new FakeWhatsAppProvider,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        RateLimiter::for('whatsapp_send', function () {
            $rate = (int) config('whatsapp.send_rate_per_minute', 30);

            return Limit::perMinute($rate);
        });

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by($email.$request->ip());
        });

        RateLimiter::for('webhooks', function () {
            return Limit::perMinute(600);
        });

        RateLimiter::for('exports', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('import_upload', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        Event::listen(
            OutcomeRecordedEvent::class,
            UpdateDriverMetricsListener::class
        );

        // Bust dashboard cache on driver, work order, and recipient changes
        Driver::saved(fn () => app(DashboardService::class)->bustCache());
        Driver::deleted(fn () => app(DashboardService::class)->bustCache());
        WorkOrder::saved(fn () => app(DashboardService::class)->bustCache());
        WorkOrder::deleted(fn () => app(DashboardService::class)->bustCache());
        WorkOrderRecipient::saved(fn () => app(DashboardService::class)->bustCache());
    }
}
