<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SessionCookieTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);

        parent::tearDown();
    }

    #[Test]
    #[TestWith(['http'])]
    #[TestWith(['https'])]
    public function production_session_cookies_are_secure_even_when_tls_terminates_before_php(string $scheme): void
    {
        $this->useSessionEnvironment('production');

        $response = $this->get($scheme.'://'.parse_url(config('app.url'), PHP_URL_HOST).'/login', [
            'X-Forwarded-Proto' => 'https',
        ]);

        $response->assertOk();

        $response->assertCookie(config('session.cookie'));

        $this->assertTrue($response->getCookie(config('session.cookie'), false)->isSecure());
    }

    #[Test]
    #[TestWith(['http', false])]
    #[TestWith(['https', true])]
    public function local_session_cookies_keep_automatic_https_detection(string $scheme, bool $secure): void
    {
        $this->useSessionEnvironment('local');

        $response = $this->get($scheme.'://localhost/login');

        $response->assertOk();

        $response->assertCookie(config('session.cookie'));

        $this->assertSame($secure, $response->getCookie(config('session.cookie'), false)->isSecure());
    }

    #[Test]
    public function arbitrary_forwarded_headers_do_not_make_an_http_request_secure(): void
    {
        $this->useSessionEnvironment('local');

        $response = $this->get('http://localhost/login', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '203.0.113.20',
            'X-Forwarded-Host' => 'attacker.example.invalid',
        ]);

        $response->assertOk();

        $response->assertCookie(config('session.cookie'));

        $this->assertFalse($response->getCookie(config('session.cookie'), false)->isSecure());

        $this->assertFalse($this->app['request']->isSecure());

        $this->assertSame('127.0.0.1', $this->app['request']->ip());

        $this->assertSame('localhost', $this->app['request']->getHost());
    }

    #[Test]
    public function secure_cookies_can_be_explicitly_enabled_for_local_http(): void
    {
        $this->useSessionEnvironment('local', 'true');

        $response = $this->get('http://localhost/login');

        $response->assertOk();

        $response->assertCookie(config('session.cookie'));

        $this->assertTrue($response->getCookie(config('session.cookie'), false)->isSecure());
    }

    private function useSessionEnvironment(string $environment, ?string $secureCookie = null): void
    {
        $originalEnvironment = $_ENV;

        $originalServer = $_SERVER;

        $originalSecureCookie = getenv('SESSION_SECURE_COOKIE');

        $_ENV['APP_ENV'] = $environment;

        $_SERVER['APP_ENV'] = $environment;

        unset($_ENV['SESSION_SECURE_COOKIE'], $_SERVER['SESSION_SECURE_COOKIE']);

        putenv('SESSION_SECURE_COOKIE');

        if ($secureCookie !== null) {
            $_ENV['SESSION_SECURE_COOKIE'] = $secureCookie;

            $_SERVER['SESSION_SECURE_COOKIE'] = $secureCookie;
        }

        try {
            $session = require base_path('config/session.php');
        } finally {
            $_ENV = $originalEnvironment;

            $_SERVER = $originalServer;

            putenv($originalSecureCookie === false ? 'SESSION_SECURE_COOKIE' : 'SESSION_SECURE_COOKIE='.
                $originalSecureCookie);
        }

        config()->set('session.secure', $session['secure']);

        $this->app->detectEnvironment(function () use ($environment): string {
            return $environment;
        });
    }
}
