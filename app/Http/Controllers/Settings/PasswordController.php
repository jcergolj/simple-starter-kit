<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Jcergolj\InAppNotifications\Facades\InAppNotification;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('settings.password.edit');
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        Auth::logoutOtherDevices($request->validated('current_password'));

        $request->user()->update([
            'password' => $request->input('password'),
        ]);

        InAppNotification::success(__('Password updated.'));

        return back();
    }
}
