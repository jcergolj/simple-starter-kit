<?php

declare(strict_types=1);

namespace App\Features\Authentication\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse;
use Laravel\Fortify\Http\Requests\VerifyEmailRequest;

class VerifyEmailController extends Controller
{
    public function __construct(private readonly VerifyEmailResponse $response) {}

    public function __invoke(VerifyEmailRequest $request): VerifyEmailResponse|RedirectResponse
    {
        $user = $request->user();

        try {
            if ((! $user->hasVerifiedEmail() || $user->pendingEmail() !== null) && $user->markEmailAsVerified()) {
                event(new Verified($user));
            }
        } catch (UniqueConstraintViolationException) {
            $user->refresh();

            return to_route('settings.profile.edit')->withErrors([
                'email' => __('validation.unique', ['attribute' => 'email']),
            ]);
        }

        return $this->response;
    }
}
