<?php

namespace App\Providers;

use App\Policies\ActivityPolicy;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Activity::class, ActivityPolicy::class);

        // Indian formats everywhere: 25-10-2026 and ₹1,02,500.00.
        Table::configureUsing(fn (Table $table) => $table
            ->defaultDateDisplayFormat('d-m-Y')
            ->defaultDateTimeDisplayFormat('d-m-Y h:i A')
            ->defaultNumberLocale('en_IN')
            ->defaultCurrency('inr'));

        Schema::configureUsing(fn (Schema $schema) => $schema
            ->defaultDateDisplayFormat('d-m-Y')
            ->defaultDateTimeDisplayFormat('d-m-Y h:i A')
            ->defaultNumberLocale('en_IN')
            ->defaultCurrency('inr'));

        DatePicker::configureUsing(fn (DatePicker $picker) => $picker
            ->native(false)
            ->displayFormat('d-m-Y')
            ->firstDayOfWeek(1)
            ->closeOnDateSelection());

        Event::listen(Login::class, function (Login $event): void {
            activity('auth')->causedBy($event->user)->event('login')->log('Logged in');
        });
    }
}
