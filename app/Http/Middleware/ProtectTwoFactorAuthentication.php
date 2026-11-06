<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

class ProtectTwoFactorAuthentication
{
    public function __construct(
        private readonly AuthenticateSession $authenticateSession,
        private readonly EnsureUserIsNotBlocked $ensureUserIsNotBlocked,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('two-factor.*') || $request->routeIs('two-factor.login', 'two-factor.login.store')) {
            return $next($request);
        }

        return $this->authenticateSession->handle($request, function (Request $request) use ($next): Response {
            return $this->ensureUserIsNotBlocked->handle($request, $next);
        });
    }
}
