<?php

declare(strict_types=1);

use App\Http\Controllers\CsrfTokenController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

require base_path('app/Features/Dashboard/Routes/web.php');
require base_path('app/Features/Invitations/Routes/web.php');

Route::get('csrf-token', [CsrfTokenController::class, 'show'])
    ->middleware(['auth', 'auth.session', 'not_blocked'])
    ->name('csrf-token');

require base_path('app/Features/Settings/Routes/web.php');
require base_path('app/Features/UserManagement/Routes/web.php');
