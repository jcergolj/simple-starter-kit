<?php

declare(strict_types=1);

use Opcodes\LogViewer\Http\Middleware\AuthorizeLogViewer;
use Opcodes\LogViewer\Http\Middleware\EnsureFrontendRequestsAreStateful;

return [
    'middleware' => [
        'web',
        'auth',
        'auth.session',
        'not_blocked',
        AuthorizeLogViewer::class,
    ],

    'api_middleware' => [
        EnsureFrontendRequestsAreStateful::class,
        'auth',
        'auth.session',
        'not_blocked',
        AuthorizeLogViewer::class,
    ],
];
