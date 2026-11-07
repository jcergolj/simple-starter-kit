<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\RoleEnum;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Opcodes\LogViewer\Facades\LogViewer;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(FilesystemManager $filesystems): void
    {
        $filesystems->extend('verified-sftp', function (Application $app, array $config) use ($filesystems): Filesystem {
            $fingerprint = $config['hostFingerprint'] ?? null;

            throw_if(! is_string($fingerprint) || ! trim($fingerprint), InvalidArgumentException::class, 'SFTP_HOST_FINGERPRINT must contain an independently verified SSH host fingerprint.');

            return $filesystems->createSftpDriver($config);
        });

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
