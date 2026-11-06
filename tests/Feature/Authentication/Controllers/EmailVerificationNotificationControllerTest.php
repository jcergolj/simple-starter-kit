<?php

declare(strict_types=1);

namespace Tests\Feature\Authentication\Controllers;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailVerificationNotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_verified_user_can_resend_verification_to_their_pending_address(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
        ]);

        $this->actingAs($user)->postJson(route('verification.send'))->assertAccepted();

        Notification::assertSentTo($user, VerifyEmail::class);

        $this->assertSame('new@example.com', $user->routeNotificationForMail(new VerifyEmail));
    }

    #[Test]
    public function an_unverified_user_can_still_request_verification(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->postJson(route('verification.send'))->assertAccepted();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    #[Test]
    public function a_verified_user_without_a_pending_address_does_not_receive_verification(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('verification.send'))->assertNoContent();

        Notification::assertNothingSent();
    }
}
