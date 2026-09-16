<?php

namespace App\Features\Settings\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Jcergolj\InAppNotifications\Facades\InAppNotification;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

class TwoFactorController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings::two-factor.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request, EnableTwoFactorAuthentication $enableTwoFactor): RedirectResponse
    {
        $enableTwoFactor($request->user());

        return to_route('settings.confirmed-two-factor.edit');
    }

    public function destroy(Request $request, DisableTwoFactorAuthentication $disableTwoFactor): RedirectResponse
    {
        $disableTwoFactor($request->user());

        InAppNotification::success(__('Two-factor disabled.'));

        return to_route('settings.two-factor.edit');
    }
}
