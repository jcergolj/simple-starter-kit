<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\AcceptInvitationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Jcergolj\FormRequestAssertions\TestableFormRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AcceptInvitationRequest::class)]
class AcceptInvitationRequestTest extends TestCase
{
    use RefreshDatabase;
    use TestableFormRequest;

    #[Test]
    public function name_is_required(): void
    {
        $this->createFormRequest(AcceptInvitationRequest::class)
            ->validate(['name' => ''])
            ->assertFails(['name' => 'required']);
    }

    #[Test]
    public function name_must_be_a_string(): void
    {
        $this->createFormRequest(AcceptInvitationRequest::class)
            ->validate(['name' => 123])
            ->assertFails(['name' => 'string']);
    }

    #[Test]
    public function name_must_not_exceed_255_characters(): void
    {
        $this->createFormRequest(AcceptInvitationRequest::class)
            ->validate(['name' => str_repeat('a', 256)])
            ->assertFails(['name' => 'max']);
    }

    #[Test]
    public function password_is_required(): void
    {
        $this->createFormRequest(AcceptInvitationRequest::class)
            ->validate(['password' => ''])
            ->assertFails(['password' => 'required']);
    }

    #[Test]
    public function password_must_be_confirmed(): void
    {
        $this->createFormRequest(AcceptInvitationRequest::class)
            ->validate(['password' => 'Secret123!', 'password_confirmation' => 'Different123!'])
            ->assertFails(['password' => 'confirmed']);
    }

    #[Test]
    public function valid_data_passes(): void
    {
        $this->createFormRequest(AcceptInvitationRequest::class)
            ->validate([
                'name' => 'Jane Doe',
                'password' => 'Secret123!',
                'password_confirmation' => 'Secret123!',
            ])
            ->assertPasses();
    }
}
