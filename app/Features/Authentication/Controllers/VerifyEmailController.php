<?php

declare(strict_types=1);

namespace App\Features\Authentication\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Laravel\Fortify\Http\Requests\VerifyEmailRequest;

class VerifyEmailController extends Controller
{
    public function __construct(private readonly VerifyEmailResponse $response) {}

    public function __invoke(VerifyEmailRequest $request): VerifyEmailResponse
    {
        $user = $request->user();

        if ((! $user->hasVerifiedEmail() || $user->pendingEmail() !== null) && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return $this->response;
    }
}
