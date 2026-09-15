<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use Illuminate\Support\Str;
use Jcergolj\InAppNotifications\Facades\InAppNotification;

class PasswordController extends Controller
{
    public function edit()
    {
        return view('settings.password.edit');
    }

    public function update(UpdatePasswordRequest $request)
    {
        $request->user()->forceFill([
            'password' => $request->input('password'),
            'remember_token' => Str::random(60),
        ])->save();

        InAppNotification::success(__('Password updated.'));

        return back();
    }
}
