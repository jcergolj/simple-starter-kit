<?php

namespace App\Features\Settings\Controllers;

use App\Features\Settings\Requests\UpdatePasswordRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Jcergolj\InAppNotifications\Facades\InAppNotification;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('settings::password.edit');
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'password' => $request->input('password'),
            'remember_token' => Str::random(60),
        ])->save();

        InAppNotification::success(__('Password updated.'));

        return back();
    }
}
