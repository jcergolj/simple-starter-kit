<?php

declare(strict_types=1);

namespace Tests\Feature\Authentication\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfirmablePasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function confirm_password_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/user/confirm-password')
            ->assertOk()
            ->assertViewIs('authentication::confirm-password')
            ->assertViewHasForm('id="confirm-password-form"', 'POST', route('password.confirm.store'))
            ->assertFormHasCSRF()
            ->assertFormHasPasswordInput('password')
            ->assertFormHasSubmitButton();
    }

    #[Test]
    public function password_can_be_confirmed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->post(route('password.confirm.store'), [
            'password' => 'password',
        ])->assertValid()->assertRedirect(route('dashboard'));
    }

    #[Test]
    public function password_is_not_confirmed_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->post(route('password.confirm.store'), [
            'password' => 'wrong-password',
        ])->assertInvalid(['password']);
    }

    #[Test]
    public function password_verification_attempts_are_shared_with_password_updates(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $this->actingAs($user);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('password.confirm.store'), [
                'password' => 'wrong-password',
            ])->assertInvalid(['password']);
        }

        $this->put(route('settings.password.update'), [
            'current_password' => 'current-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertTooManyRequests();

        $this->assertTrue(Hash::check('current-password', $user->refresh()->password));
    }

    #[Test]
    public function password_verification_attempts_are_isolated_between_accounts(): void
    {
        $blockedUser = User::factory()->create([
            'password' => Hash::make('blocked-password'),
        ]);
        $otherUser = User::factory()->create([
            'password' => Hash::make('other-password'),
        ]);

        $this->actingAs($blockedUser);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('password.confirm.store'), [
                'password' => 'wrong-password',
            ])->assertInvalid(['password']);
        }

        $this->actingAs($otherUser)
            ->post(route('password.confirm.store'), [
                'password' => 'other-password',
            ])->assertRedirect(route('dashboard'));
    }
}
