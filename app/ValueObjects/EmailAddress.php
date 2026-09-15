<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Str;

final readonly class EmailAddress implements \Stringable
{
    private function __construct(
        private string $value,
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
