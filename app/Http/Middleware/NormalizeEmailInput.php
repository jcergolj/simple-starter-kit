<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\NormalizeEmail;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeEmailInput
{
    public function handle(Request $request, Closure $next): Response
    {
        if (is_string($request->input('email'))) {
            $request->merge([
                'email' => NormalizeEmail::normalize($request->input('email')),
            ]);
        }

        return $next($request);
    }
}
