<?php

declare(strict_types=1);

use App\Http\Controllers\CsrfTokenController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('csrf-token', [CsrfTokenController::class, 'show'])
    ->middleware(['auth', 'auth.session', 'not_blocked'])
    ->name('csrf-token');
