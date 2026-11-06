<?php

declare(strict_types=1);

namespace App\Features\Authentication\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\EmailVerificationNotificationSentResponse;
use Laravel\Fortify\Http\Responses\RedirectAsIntended;

class EmailVerificationNotificationController extends Controller
{
    public function __construct(private readonly EmailVerificationNotificationSentResponse $response) {}

    public function store(Request $request): EmailVerificationNotificationSentResponse|JsonResponse|RedirectAsIntended
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail() && $user->pendingEmail() === null) {
            return $request->wantsJson()
                ? new JsonResponse('', 204)
                : new RedirectAsIntended('email-verification');
        }

        $user->sendEmailVerificationNotification();

        return $this->response;
    }
}
