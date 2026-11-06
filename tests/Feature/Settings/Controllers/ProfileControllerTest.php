<?php

declare(strict_types=1);

namespace Tests\Feature\Settings\Controllers;

use App\Features\Settings\Controllers\ProfileController;
use App\Features\Settings\Notifications\EmailChangedNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[CoversClass(ProfileController::class)]
class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    #[Test]
    public function edit_requires_authentication(): void
    {
        $response = $this->get(route('settings.profile.edit'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function update_updates_user_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => Carbon::now()->getTimestamp()])
            ->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => 'New Name',
                'email' => 'new@example.com',
            ]);

        $response->assertRedirect();

        $user->refresh();

        $this->assertEquals('New Name', $user->name);

        $this->assertEquals('old@example.com', $user->email);

        $this->assertEquals('new@example.com', $user->pending_email);
    }

    #[Test]
    public function update_keeps_the_current_email_as_recovery_until_replacement_is_verified(): void
    {
        $user = User::factory()->create([
            'name' => 'Test Name',
            'email' => 'old@example.com',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => Carbon::now()->getTimestamp()])
            ->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => 'Test Name',
                'email' => 'new@example.com',
            ]);

        $response->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);

        $this->assertSame('new@example.com', $user->pending_email);

        Notification::assertSentTo($user, VerifyEmail::class);

        Notification::assertSentTo($user, EmailChangedNotification::class);
    }

    #[Test]
    public function a_user_can_follow_the_redirect_and_correct_a_pending_email(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $verifiedAt = $user->email_verified_at->toDateTimeString();

        $this->actingAs($user)->post(route('password.confirm.store'), [
            'password' => 'password',
        ])->assertValid();

        $response = $this->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => $user->name,
                'email' => 'typo@example.com',
            ]);

        $response->assertRedirect(route('settings.profile.edit'));

        $this->followRedirects($response)
            ->assertOk()
            ->assertViewIs('settings::profile.edit')
            ->assertSee('Your new email address is awaiting verification.')
            ->assertSee('Your current email address is old@example.com. To cancel the pending change, enter this address and save.')
            ->assertSee('Re-send the verification email')
            ->assertViewHas('email', 'typo@example.com');

        $oldVerificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1('typo@example.com')],
        );

        $response = $this->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => $user->name,
                'email' => 'correct@example.com',
            ]);

        $response->assertRedirect(route('settings.profile.edit'));

        $this->followRedirects($response)
            ->assertOk()
            ->assertViewHas('email', 'correct@example.com');

        $savedUser = $user->fresh();

        $this->assertSame('old@example.com', $savedUser->email);

        $this->assertSame('correct@example.com', $savedUser->pending_email);

        $this->assertSame($verifiedAt, $savedUser->email_verified_at->toDateTimeString());

        $this->get($oldVerificationUrl)->assertForbidden();
    }

    #[Test]
    public function a_user_can_log_in_again_and_cancel_a_pending_email(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $verifiedAt = $user->email_verified_at->toDateTimeString();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->post(route('password.confirm.store'), ['password' => 'password'])->assertValid();

        $response = $this->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => $user->name,
                'email' => 'typo@example.com',
            ]);

        $this->followRedirects($response)->assertOk()->assertViewIs('settings::profile.edit');

        $oldVerificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1('typo@example.com')],
        );

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->post(route('login.store'), [
            'email' => 'old@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('settings.profile.edit'))->assertRedirect(route('password.confirm'));

        $this->post(route('password.confirm.store'), ['password' => 'password'])->assertValid();

        $this->get(route('settings.profile.edit'))->assertOk()->assertViewHas('email', 'typo@example.com');

        $response = $this->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => $user->name,
                'email' => 'old@example.com',
            ]);

        $response->assertRedirect(route('settings.profile.edit'));

        $this->followRedirects($response)->assertOk()->assertViewHas('email', 'old@example.com');

        $savedUser = $user->fresh();

        $this->assertSame('old@example.com', $savedUser->email);

        $this->assertNull($savedUser->pending_email);

        $this->assertSame($verifiedAt, $savedUser->email_verified_at->toDateTimeString());

        $this->get($oldVerificationUrl)->assertForbidden();

        Notification::assertSentToTimes($savedUser, VerifyEmail::class, 1);
    }

    #[Test]
    #[TestWith(['correct@example.com', 'correct@example.com', 'settings::profile.edit', 'dashboard'])]
    #[TestWith(['old@example.com', null, 'authentication::verify-email', 'settings.profile.edit'])]
    public function a_user_locked_out_by_an_earlier_email_change_can_correct_or_cancel_it(
        string $email,
        ?string $pendingEmail,
        string $view,
        string $redirect,
    ): void {
        $user = User::factory()->unverified()->create([
            'email' => 'old@example.com',
            'pending_email' => 'typo@example.com',
        ]);

        $this->actingAs($user)->get(route('settings.profile.edit'))->assertRedirect(route('password.confirm'));

        $this->post(route('password.confirm.store'), ['password' => 'password'])->assertValid();

        $this->get(route('settings.profile.edit'))->assertOk()->assertViewHas('email', 'typo@example.com');

        $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));

        $response = $this->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => $user->name,
                'email' => $email,
            ]);

        $this->followRedirects($response)->assertOk()->assertViewIs($view);

        $savedUser = $user->fresh();

        $this->assertSame('old@example.com', $savedUser->email);

        $this->assertSame($pendingEmail, $savedUser->pending_email);

        $this->assertNull($savedUser->email_verified_at);

        Notification::assertSentTo($savedUser, VerifyEmail::class);

        $this->assertSame($email, $savedUser->routeNotificationForMail(new VerifyEmail));

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1($email)],
        );

        $response = $this->get($verificationUrl)->assertRedirect(route($redirect));

        $this->followRedirects($response)->assertOk();

        $savedUser = $user->fresh();

        $this->assertSame($email, $savedUser->email);

        $this->assertNull($savedUser->pending_email);

        $this->assertNotNull($savedUser->email_verified_at);
    }

    #[Test]
    public function an_unverified_user_without_a_pending_change_cannot_edit_their_profile(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('settings.profile.edit'))->assertRedirect(route('verification.notice'));

        $this->patch(route('settings.profile.update'), [
            'name' => $user->name,
            'email' => 'new@example.com',
        ])->assertRedirect(route('verification.notice'));

        $this->assertNull($user->fresh()->pending_email);
    }

    #[Test]
    public function update_requires_authentication(): void
    {
        $response = $this->patch(route('settings.profile.update'), [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function update_requires_recent_password_confirmation(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $response = $this->actingAs($user)->patch(route('settings.profile.update'), [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        $response->assertRedirect(route('password.confirm'));

        $this->assertSame('old@example.com', $user->refresh()->email);

        $this->assertNull($user->pending_email);
    }

    #[Test]
    public function incorrect_password_confirmation_does_not_change_email(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)->post(route('password.confirm.store'), [
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/')
            ->assertSessionHasErrors('password');

        $this->assertSame('old@example.com', $user->refresh()->email);

        $this->assertNull($user->pending_email);
    }

    #[Test]
    public function verified_replacement_becomes_the_recovery_email(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'email_verified_at' => now(),
            'pending_email' => 'new@example.com',
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1('new@example.com')],
        );

        $this->actingAs($user)->get($verificationUrl)->assertRedirect(route('dashboard').'?verified=1');

        $user->refresh();

        $this->assertSame('new@example.com', $user->email);

        $this->assertNull($user->pending_email);

        $this->assertNotNull($user->email_verified_at);
    }

    #[Test]
    public function delete_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['auth.password_confirmed_at' => Carbon::now()->getTimestamp()]);

        $this->get(route('settings.profile.delete'))
            ->assertOk()
            ->assertViewIs('settings::profile.delete')
            ->assertViewHasForm('id="delete-profile-form"', 'POST', route('settings.profile.destroy'))
            ->assertFormHasCSRF()
            ->assertFormHasPasswordInput('password')
            ->assertFormHasSubmitButton();
    }

    #[Test]
    public function delete_requires_authentication(): void
    {
        $response = $this->get(route('settings.profile.delete'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function destroy_deletes_user_account(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)
            ->from(route('settings.profile.delete'))
            ->post(route('settings.profile.destroy'), [
                'password' => 'password',
            ]);

        $response->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        $this->assertGuest();
    }

    #[Test]
    public function destroy_requires_authentication(): void
    {
        $response = $this->post(route('settings.profile.destroy'), [
            'password' => 'password',
        ]);

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function destroy_validates_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)
            ->from(route('settings.profile.delete'))
            ->post(route('settings.profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response->assertRedirect(route('settings.profile.delete'))
            ->assertSessionHasErrors(['password']);
    }

    #[Test]
    public function profile_page_is_displayed(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['auth.password_confirmed_at' => Carbon::now()->getTimestamp()]);

        $this->get(route('settings.profile.edit'))
            ->assertOk()
            ->assertViewIs('settings::profile.edit')
            ->assertViewHasForm('id="edit-profile-form"', 'PUT', route('settings.profile.update'))
            ->assertFormHasCSRF()
            ->assertFormHasTextInput('name')
            ->assertFormHasEmailInput('email')
            ->assertFormHasSubmitButton();
    }

    #[Test]
    public function profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => Carbon::now()->getTimestamp()]);

        $this->put(route('settings.profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])->assertValid();

        $user->refresh();

        $this->assertEquals('Test User', $user->name);

        $this->assertEquals('test@example.com', $user->pending_email);

        $this->assertNotNull($user->email_verified_at);
    }

    #[Test]
    public function email_verification_status_is_unchanged_when_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => Carbon::now()->getTimestamp()]);

        $this->put(route('settings.profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ])->assertValid();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    #[Test]
    public function user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => Carbon::now()->getTimestamp()]);

        $this->post(route('settings.profile.destroy'), [
            'password' => 'password',
        ])->assertValid()->assertRedirect('/');

        $this->assertGuest();

        $this->assertModelMissing($user);
    }

    #[Test]
    public function correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->post(route('settings.profile.destroy'), [
            'password' => 'wrong-password',
        ])->assertInvalid(['password']);

        $this->assertModelExists($user);
    }
}
