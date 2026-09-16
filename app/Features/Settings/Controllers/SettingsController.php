<?php

declare(strict_types=1);

namespace App\Features\Settings\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function show(): View
    {
        return view('settings::menu');
    }
}
