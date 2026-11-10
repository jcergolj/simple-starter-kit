<?php

declare(strict_types=1);

namespace Tests\Feature\Authentication\Controllers;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Uri;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PasswordResetLinkControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function json_recovery_responses_do_not_reveal_known_unknown_or_recently_requested_accounts(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        foreach ([$user->email, 'missing@example.com', $user->email] as $email) {
            $this->postJson(route('password.email'), ['email' => $email])
                ->assertOk()
                ->assertExactJson([
                    'message' => __('If an account exists for this email address, you will receive a password reset link.'),
                ]);
        }

        Notification::assertSentTo($user, ResetPassword::class);

        Notification::assertCount(1);
    }

    #[Test]
    public function html_recovery_responses_do_not_reveal_known_unknown_or_recently_requested_accounts(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        foreach ([$user->email, 'missing@example.com', $user->email] as $email) {
            $this->from(route('password.request'))->post(route('password.email'), ['email' => $email])
                ->assertRedirect(route('password.request'))
                ->assertSessionHasNoErrors()
                ->assertSessionHas(
                    'status',
                    __('If an account exists for this email address, you will receive a password reset link.'),
                );
        }

        Notification::assertSentTo($user, ResetPassword::class);

        Notification::assertCount(1);
    }

    #[Test]
    public function recovery_requests_share_an_ip_limit_across_email_addresses(): void
    {
        Notification::fake();

        $this->freezeTime();

        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson(route('password.email'), ['email' => 'missing'.$attempt.'@example.com'])
                ->assertOk();
        }

        $this->postJson(route('password.email'), ['email' => $user->email])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');

        Notification::assertNothingSent();

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->postJson(route('password.email'), ['email' => $user->email])
            ->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);

        Notification::assertCount(1);

        $this->travel(61)->seconds();

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson(route('password.email'), ['email' => $user->email])
            ->assertOk();

        Notification::assertCount(2);
    }

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);

        parent::tearDown();
    }

    #[Test]
    #[TestWith(['attacker.example.invalid'])]
    #[TestWith(['evil.accounts.example.test'])]
    #[TestWith(['accountsXexample.test'])]
    #[TestWith(['accounts.example.test.attacker.example.invalid'])]
    public function production_rejects_untrusted_hosts(string $host): void
    {
        config()->set('app.url', 'https://accounts.example.test');

        $this->app->detectEnvironment(function (): string {
            return 'production';
        });

        $this->get('https://'.$host.'/forgot-password')->assertBadRequest();
    }

    #[Test]
    public function production_accepts_the_configured_host_and_ignores_untrusted_forwarded_hosts(): void
    {
        config()->set('app.url', 'https://accounts.example.test');

        $this->app->detectEnvironment(function (): string {
            return 'production';
        });

        $this->get('https://accounts.example.test/forgot-password', [
            'X-Forwarded-Host' => 'attacker.example.invalid',
        ])->assertOk();
    }

    #[Test]
    public function reset_links_use_the_configured_origin_even_with_an_untrusted_request_host(): void
    {
        Notification::fake();

        config()->set('app.url', 'https://accounts.example.test:8443');

        $user = User::factory()->create(['email' => 'alice+recovery@example.com']);

        $this->post('http://attacker.example.invalid/forgot-password', [
            'email' => $user->email,
        ])->assertValid();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $url = (string) $notification->toMail($user)->actionUrl;

            $uri = Uri::of($url);

            $this->assertSame('https', $uri->scheme());

            $this->assertSame('accounts.example.test', $uri->host());

            $this->assertSame(8443, $uri->port());

            $this->assertSame('reset-password/'.$notification->token, $uri->path());

            $this->assertSame('alice+recovery@example.com', $uri->query()->get('email'));

            $this->get($url)->assertOk();

            $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])->assertValid()->assertRedirect(route('login'));

            $savedUser = $user->fresh();

            $this->assertTrue(Hash::check('NewPassword123!', $savedUser->password));

            return true;
        });
    }

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

        $this->post(route('password.email'), ['email' => 'new@example.com'])->assertValid();

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
