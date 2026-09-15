<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\ValueObjects\EmailAddress;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeEmailInput
{
    public function handle(Request $request, Closure $next): Response
    {
        if (is_string($request->input('email'))) {
            $request->merge([
                'email' => EmailAddress::from($request->input('email'))->toString(),
            ]);
        }

        return $next($request);
    }
}
