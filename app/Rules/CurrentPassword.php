<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class CurrentPassword implements ValidationRule
{
    private const int MAX_ATTEMPTS = 5;

    private const int DECAY_SECONDS = 60;

    public function __construct(
        private readonly User $user,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->verify($value)) {
            $fail(__('The provided password does not match your current password.'));
        }
    }

    public function verify(?string $password): bool
    {
        $key = $this->limiterKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new TooManyRequestsHttpException(
                RateLimiter::availableIn($key),
                'Too many password verification attempts.'
            );
        }

        if (Hash::check($password, $this->user->getAuthPassword())) {
            return true;
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        return false;
    }

    private function limiterKey(): string
    {
        return 'password-verification:'.$this->user->getAuthIdentifier();
    }
}
