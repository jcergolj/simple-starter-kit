<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerifiedOrPending
{
    public function __construct(private readonly EnsureEmailIsVerified $ensureEmailIsVerified) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->pendingEmail() !== null) {
            return $next($request);
        }

        return $this->ensureEmailIsVerified->handle($request, $next);
    }
}
