<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Str;

class NormalizeEmail
{
    public static function normalize(string $email): string
    {
        return Str::lower(trim($email));
    }
}
