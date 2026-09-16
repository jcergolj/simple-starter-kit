<?php

declare(strict_types=1);

namespace Tests\Feature\Authentication\Controllers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordResetLinkControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function reset_password_link_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertViewIs('authentication::forgot-password')
            ->assertViewHasForm('id="forgot-password-form"', 'POST', route('password.email'))
            ->assertFormHasCSRF()
            ->assertFormHasEmailInput('email')
            ->assertFormHasSubmitButton();
    }

    #[Test]
    public function reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.request'), [
            'email' => $user->email,
        ])->assertValid();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    #[Test]
    public function reset_password_link_can_be_requested_with_mixed_case_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'alice@example.com']);

        $this->post(route('password.request'), ['email' => 'ALICE@EXAMPLE.COM'])
            ->assertValid();

        Notification::assertSentTo($user, ResetPassword::class);
    }
}
