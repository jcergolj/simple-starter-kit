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
    public function a_pending_email_cannot_be_used_for_password_recovery(): void
    {
        Notification::fake();

        User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
        ]);

        $this->post(route('password.email'), ['email' => 'new@example.com'])->assertInvalid('email');

        Notification::assertNothingSent();
    }

    #[Test]
    public function the_current_email_remains_the_password_recovery_address_while_a_change_is_pending(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
        ]);

        $this->post(route('password.email'), ['email' => 'old@example.com'])->assertValid();

        Notification::assertSentTo($user, ResetPassword::class);

        $this->assertSame('old@example.com', $user->routeNotificationForMail(new ResetPassword('token')));
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
