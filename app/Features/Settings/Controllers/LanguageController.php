<?php

namespace App\Features\Settings\Controllers;

use App\DataTransferObjects\UserSettings;
use App\Features\Settings\Requests\SaveLanguageRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Jcergolj\InAppNotifications\Facades\InAppNotification;

class LanguageController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings::language.edit', [
            'currentLang' => $request->user()->settings->lang,
        ]);
    }

    public function update(SaveLanguageRequest $request): RedirectResponse
    {
        $request->user()->update([
            'settings' => new UserSettings(lang: $request->input('lang')),
        ]);

        InAppNotification::success(__('Language updated.'));

        return back();
    }
}
