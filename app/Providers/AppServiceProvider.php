<?php

namespace App\Providers;

use App\Models\Loan;
use App\Observers\LoanObserver;
use App\Support\Currency;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Empresa activa: una instancia por petición (se reinicia en cada request / job).
        $this->app->scoped(\App\Support\Tenancy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use Bootstrap 5 pagination views across all modules.
        Paginator::useBootstrapFive();

        // Notificaciones push al crear préstamos.
        Loan::observe(LoanObserver::class);

        // Símbolo de moneda de la empresa actual en todas las vistas ({{ $currencySymbol }}).
        // Si el controlador ya lo pasa (p. ej. catálogo público, sin usuario), se respeta.
        View::composer('*', function ($view) {
            if (! array_key_exists('currencySymbol', $view->getData())) {
                $view->with('currencySymbol', Currency::symbol());
            }
        });
    }
}
