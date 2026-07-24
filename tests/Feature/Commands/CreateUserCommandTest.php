<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\RoleEnum;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_regular_user_directly(): void
    {
        $this->artisan('app:create-user')
            ->expectsChoice(__('User role?'), __('User'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Create directly'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Name'), 'John Doe')
            ->expectsQuestion(__('Email'), 'john@example.com')
            ->expectsQuestion(__('Password'), 'password')
            ->assertSuccessful();

        $user = User::where('email', 'john@example.com')->first();

        $this->assertSame('John Doe', $user->name);

        $this->assertTrue(Hash::check('password', $user->password));

        $this->assertSame(RoleEnum::User, $user->role);

        $this->assertNotNull($user->email_verified_at);
    }

    #[Test]
    public function it_creates_admin_directly(): void
    {
        $this->artisan('app:create-user')
            ->expectsChoice(__('User role?'), __('Admin'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Create directly'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Name'), 'Admin User')
            ->expectsQuestion(__('Email'), 'admin@example.com')
            ->expectsQuestion(__('Password'), 'password')
            ->assertSuccessful();

        $user = User::where('email', 'admin@example.com')->first();
        $this->assertSame('Admin User', $user->name);

        $this->assertTrue(Hash::check('password', $user->password));

        $this->assertSame(RoleEnum::Admin, $user->role);

        $this->assertNotNull($user->email_verified_at);
    }

    #[Test]
    public function it_creates_superadmin_directly(): void
    {
        $this->artisan('app:create-user')
            ->expectsChoice(__('User role?'), __('Superadmin'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Create directly'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Name'), 'Super Admin')
            ->expectsQuestion(__('Email'), 'superadmin@example.com')
            ->expectsQuestion(__('Password'), 'password')
            ->assertSuccessful();

        $user = User::where('email', 'superadmin@example.com')->first();
        $this->assertSame('Super Admin', $user->name);

        $this->assertTrue(Hash::check('password', $user->password));

        $this->assertSame(RoleEnum::Superadmin, $user->role);

        $this->assertNotNull($user->email_verified_at);
    }

    #[Test]
    public function it_sends_invitation_for_regular_user(): void
    {
        Mail::fake();

        $this->artisan('app:create-user')
            ->expectsChoice(__('User role?'), __('User'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Send invitation'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Email'), 'invite@example.com')
            ->expectsChoice(__('Language'), 'en', ['en', 'sl'])
            ->assertSuccessful();

        $invitation = Invitation::where('email', 'invite@example.com')->first();
        $this->assertNotNull($invitation);

        $this->assertSame(RoleEnum::User, $invitation->role);

        Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) {
            return $mail->invitation->email === 'invite@example.com';
        });
    }

    #[Test]
    public function it_sends_invitation_for_admin(): void
    {
        Mail::fake();

        $this->artisan('app:create-user')
            ->expectsChoice(__('User role?'), __('Admin'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Send invitation'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Email'), 'admin-invite@example.com')
            ->expectsChoice(__('Language'), 'en', ['en', 'sl'])
            ->assertSuccessful();

        $invitation = Invitation::where('email', 'admin-invite@example.com')->first();
        $this->assertNotNull($invitation);

        $this->assertSame(RoleEnum::Admin, $invitation->role);

        Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) {
            return $mail->invitation->email === 'admin-invite@example.com';
        });
    }

    #[Test]
    public function it_sends_invitation_for_superadmin(): void
    {
        Mail::fake();

        $this->artisan('app:create-user')
            ->expectsChoice(__('User role?'), __('Superadmin'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Send invitation'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Email'), 'superadmin-invite@example.com')
            ->expectsChoice(__('Language'), 'en', ['en', 'sl'])
            ->assertSuccessful();

        $invitation = Invitation::where('email', 'superadmin-invite@example.com')->first();
        $this->assertNotNull($invitation);

        $this->assertSame(RoleEnum::Superadmin, $invitation->role);

        Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) {
            return $mail->invitation->email === 'superadmin-invite@example.com';
        });
    }

    #[Test]
    public function it_stores_selected_language_on_invitation(): void
    {
        Mail::fake();

        $this->artisan('app:create-user')
            ->expectsChoice(__('User role?'), __('User'), [
                __('User'),
                __('Admin'),
                __('Superadmin'),
            ])
            ->expectsChoice(__('How should the user be created?'), __('Send invitation'), [
                __('Send invitation'),
                __('Create directly'),
            ])
            ->expectsQuestion(__('Email'), 'lang-test@example.com')
            ->expectsChoice(__('Language'), 'sl', ['en', 'sl'])
            ->assertSuccessful();

        $invitation = Invitation::where('email', 'lang-test@example.com')->first();
        $this->assertSame('sl', $invitation->lang);
    }
}
