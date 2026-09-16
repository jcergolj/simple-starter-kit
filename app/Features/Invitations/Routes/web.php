<?php

declare(strict_types=1);

use App\Features\Invitations\Controllers\AcceptInvitationController;
use App\Features\Invitations\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::get('invite/{token}', [AcceptInvitationController::class, 'show'])
    ->name('invitations.accept');
Route::post('invite/{token}', [AcceptInvitationController::class, 'store'])
    ->name('accept.invitations.store');

Route::middleware(['auth', 'auth.session', 'verified', 'not_blocked', 'admin'])->group(function () {
    Route::get('invitations/create', [InvitationController::class, 'create'])
        ->name('invitations.create');
    Route::post('invitations', [InvitationController::class, 'store'])
        ->name('invitations.store');
    Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])
        ->name('invitations.destroy');
});
