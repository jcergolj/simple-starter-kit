<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class FeatureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::addNamespace('authentication', base_path('app/Features/Authentication/Views'));
        View::addNamespace('dashboard', base_path('app/Features/Dashboard/Views'));
        View::addNamespace('invitations', base_path('app/Features/Invitations/Views'));
        View::addNamespace('settings', base_path('app/Features/Settings/Views'));
        View::addNamespace('user-management', base_path('app/Features/UserManagement/Views'));
    }
}
