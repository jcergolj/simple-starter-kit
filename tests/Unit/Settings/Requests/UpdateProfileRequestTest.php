<?php

declare(strict_types=1);

namespace Tests\Unit\Settings\Requests;

use App\Features\Settings\Requests\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Jcergolj\FormRequestAssertions\TestableFormRequest;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UpdateProfileRequestTest extends TestCase
{
    use RefreshDatabase;
    use TestableFormRequest;

    #[Test]
    public function email_cannot_be_pending_for_another_user(): void
    {
        $user = User::factory()->create();

        User::factory()->create(['pending_email' => 'pending@example.com']);

        $this->createFormRequest(UpdateProfileRequest::class)
            ->by($user)
            ->validate(['name' => $user->name, 'email' => ' Pending@Example.COM '])
            ->assertFails(['email' => 'unique']);
    }

    #[Test]
    public function user_can_keep_their_own_pending_email(): void
    {
        $user = User::factory()->create(['pending_email' => 'pending@example.com']);

        $this->createFormRequest(UpdateProfileRequest::class)
            ->by($user)
            ->validate(['name' => $user->name, 'email' => 'pending@example.com'])
            ->assertPasses();
    }
}
