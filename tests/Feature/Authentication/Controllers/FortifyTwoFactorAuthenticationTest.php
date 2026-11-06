<?php

declare(strict_types=1);

namespace Tests\Feature\Authentication\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FortifyTwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withTwoFactorAuthenticationEnabled()->create();
    }

    /** @return \Iterator<string, array{string, string}> */
    public static function twoFactorOperations(): \Iterator
    {
        yield 'enable' => ['POST', 'two-factor.enable'];
        yield 'confirm' => ['POST', 'two-factor.confirm'];
        yield 'disable' => ['DELETE', 'two-factor.disable'];
        yield 'QR code' => ['GET', 'two-factor.qr-code'];
        yield 'secret key' => ['GET', 'two-factor.secret-key'];
        yield 'recovery codes' => ['GET', 'two-factor.recovery-codes'];
        yield 'regenerate recovery codes' => ['POST', 'two-factor.regenerate-recovery-codes'];
    }

    #[Test]
    #[DataProvider('twoFactorOperations')]
    public function password_reset_stops_an_existing_session_from_managing_two_factor_authentication(
        string $method,
        string $route,
    ): void {
        $this->signInAndConfirmPassword();

        $session = session()->all();

        $secret = $this->user->two_factor_secret;

        $recoveryCodes = $this->user->two_factor_recovery_codes;

        $token = Password::createToken($this->user);

        $this->flushSession();
        Auth::forgetGuards();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $this->user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertValid()->assertRedirect(route('login'));

        $this->withSession($session);
        Auth::forgetGuards();

        $this->call($method, route($route))->assertRedirect(route('login'));

        $this->assertGuest();

        $user = $this->user->fresh();

        $this->assertSame($secret, $user->two_factor_secret);

        $this->assertSame($recoveryCodes, $user->two_factor_recovery_codes);

        $this->assertNotNull($user->two_factor_confirmed_at);
    }

    #[Test]
    #[DataProvider('twoFactorOperations')]
    public function blocking_a_user_stops_their_existing_session_from_managing_two_factor_authentication(
        string $method,
        string $route,
    ): void {
        $this->signInAndConfirmPassword();

        $secret = $this->user->two_factor_secret;

        $recoveryCodes = $this->user->two_factor_recovery_codes;

        $this->user->update(['blocked_at' => now()]);
        Auth::forgetGuards();

        $this->call($method, route($route))->assertForbidden();

        $user = $this->user->fresh();

        $this->assertSame($secret, $user->two_factor_secret);

        $this->assertSame($recoveryCodes, $user->two_factor_recovery_codes);

        $this->assertNotNull($user->two_factor_confirmed_at);
    }

    #[Test]
    public function a_current_session_can_read_recovery_codes_and_disable_two_factor_authentication(): void
    {
        $this->signInAndConfirmPassword();
        Auth::forgetGuards();

        $this->getJson(route('two-factor.recovery-codes'))
            ->assertOk()
            ->assertJsonFragment(['recovery-code-2']);

        $this->deleteJson(route('two-factor.disable'))->assertOk();

        $user = $this->user->fresh();

        $this->assertNull($user->two_factor_secret);

        $this->assertNull($user->two_factor_recovery_codes);

        $this->assertNull($user->two_factor_confirmed_at);
    }

    #[Test]
    public function managing_two_factor_authentication_still_requires_password_confirmation(): void
    {
        $this->signInAndConfirmPassword();

        $this->withSession(['auth.password_confirmed_at' => now()->subHours(4)->timestamp]);
        Auth::forgetGuards();

        $this->get(route('two-factor.recovery-codes'))->assertRedirect(route('password.confirm'));
    }

    #[Test]
    public function a_blocked_user_with_a_revoked_session_can_still_log_out(): void
    {
        $this->signInAndConfirmPassword();

        $this->user->update([
            'password' => 'new-password',
            'blocked_at' => now(),
        ]);
        Auth::forgetGuards();

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
    }

    private function signInAndConfirmPassword(): void
    {
        $this->post(route('login.store'), [
            'email' => $this->user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $this->post(route('two-factor.login.store'), [
            'recovery_code' => 'recovery-code-1',
        ])->assertRedirect(route('dashboard'));

        $this->post(route('password.confirm.store'), [
            'password' => 'password',
        ])->assertValid();

        $this->user->refresh();
    }
}
