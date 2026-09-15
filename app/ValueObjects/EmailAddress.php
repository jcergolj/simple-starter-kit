<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Str;

final class EmailAddress
{
    private function __construct(
        private readonly string $value,
    ) {}

    public static function from(?string $email): self
    {
        return new self(
            Str::of($email ?? '')
                ->trim()
                ->lower()
                ->toString(),
        );
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
