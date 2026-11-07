<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Authentication\Actions\Fortify\ResetUserPassword;
use App\Features\Authentication\Controllers\EmailVerificationNotificationController;
use App\Features\Authentication\Controllers\PasswordResetLinkController;
use App\Features\Authentication\Controllers\VerifyEmailController;
use App\Features\Authentication\Responses\PasswordResetLinkResponse;
use App\Models\User;
use App\Rules\CurrentPassword;
use App\ValueObjects\EmailAddress;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Controllers\EmailVerificationNotificationController as FortifyEmailVerificationNotificationController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController as FortifyPasswordResetLinkController;
use Laravel\Fortify\Http\Controllers\VerifyEmailController as FortifyVerifyEmailController;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, PasswordResetLinkResponse::class);
        $this->app->bind(FortifyPasswordResetLinkController::class, PasswordResetLinkController::class);

        $this->app->bind(FortifyVerifyEmailController::class, VerifyEmailController::class);
        $this->app
            ->bind(
                FortifyEmailVerificationNotificationController::class,
                EmailVerificationNotificationController::class,
            );
    }

    public function boot(): void
    {
        $this->configureFotifyActions();
        $this->configureFortifyViews();
        $this->configureRateLimiting();

        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return rtrim(config('app.url'), '/').route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], absolute: false);
        });
    }

    private function configureFortifyViews(): void
    {
        Fortify::twoFactorChallengeView(function () {
            return view('authentication::two-factor-challenge');
        });
        Fortify::confirmPasswordView(function () {
            return view('authentication::confirm-password');
        });
        Fortify::loginView(function () {
            return view('authentication::login');
        });
        Fortify::verifyEmailView(function () {
            return view('authentication::verify-email');
        });
        Fortify::resetPasswordView(function () {
            return view('authentication::reset-password');
        });
        Fortify::requestPasswordResetLinkView(function () {
            return view('authentication::forgot-password');
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('password-recovery', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }

    private function configureFotifyActions(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::where('email', EmailAddress::from($request->input('email', ''))->toString())->first();

            if ($user?->isBlocked()) {
                return null;
            }

            return $user && Hash::check($request->input('password'), $user->password)
                ? $user
                : null;
        });
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::confirmPasswordsUsing(function (User $user, ?string $password): bool {
            return (new CurrentPassword($user))->verify($password);
        });
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);
    }
}
