<?php

namespace App\Features\Settings\Controllers;

use App\Features\Settings\Notifications\EmailChangedNotification;
use App\Features\Settings\Requests\DeleteProfileRequest;
use App\Features\Settings\Requests\UpdateProfileRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Jcergolj\InAppNotifications\Facades\InAppNotification;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings::profile.edit', [
            'name' => $request->user()->name,
            'email' => $request->user()->pendingEmail() ?? $request->user()->email,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $attributes = $request->validated();

        $user->name = $attributes['name'];

        if ($attributes['email'] !== $user->email) {
            $user->pending_email = $attributes['email'];
            $user->email_verified_at = null;
            $user->save();
            $user->sendEmailVerificationNotification();
            $user->notify(new EmailChangedNotification($attributes['email']));
        } else {
            $user->pending_email = null;
            $user->save();
        }

        InAppNotification::success(__('Your profile has been updated.'));

        return back();
    }

    public function delete(): View
    {
        return view('settings::profile.delete');
    }

    public function destroy(DeleteProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();

        $user->delete();

        Session::invalidate();
        Session::regenerateToken();

        return redirect('/');
    }
}
