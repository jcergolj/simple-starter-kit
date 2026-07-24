<?php

namespace App\Providers;

use App\Enums\RoleEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Mail::extend('brevo', function () {
            return (new BrevoTransportFactory)->create(
                new Dsn(
                    'brevo+api',
                    'default',
                    config('services.brevo.key')
                )
            );
        });

        LogViewer::auth(function ($request) {
            return $request->user()?->role === RoleEnum::Superadmin;
        });

        DB::prohibitDestructiveCommands($this->app->isProduction());

        if (! $this->app->isLocal() && ! $this->app->environment('testing')) {
            URL::forceScheme('https');
        }
    }
}
