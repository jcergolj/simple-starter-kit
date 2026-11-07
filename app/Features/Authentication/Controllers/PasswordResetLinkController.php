<?php

declare(strict_types=1);

namespace App\Features\Authentication\Controllers;

use Laravel\Fortify\Http\Controllers\PasswordResetLinkController as FortifyPasswordResetLinkController;

class PasswordResetLinkController extends FortifyPasswordResetLinkController
{
    public function __construct()
    {
        $this->middleware('throttle:password-recovery')->only('store');
    }
}
