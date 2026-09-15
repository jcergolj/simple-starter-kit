<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\EmailAddress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(EmailAddress::class)]
class EmailAddressTest extends TestCase
{
    #[Test]
    public function it_normalizes_an_email_address(): void
    {
        $email = EmailAddress::from('  Alice@Example.COM ');

        $this->assertSame('alice@example.com', $email->toString());
        $this->assertSame('alice@example.com', (string) $email);
    }

    #[Test]
    public function it_supports_missing_email_input(): void
    {
        $this->assertSame('', EmailAddress::from(null)->toString());
    }
}
