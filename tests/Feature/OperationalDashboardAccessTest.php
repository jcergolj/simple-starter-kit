<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OperationalDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->detectEnvironment(function (): string {
            return 'production';
        });
    }

    #[Test]
    #[TestWith(['horizon.index'])]
    #[TestWith(['log-viewer.index'])]
    #[TestWith(['log-viewer.hosts'])]
    public function stale_password_sessions_are_rejected(string $route): void
    {
        $user = User::factory()->superadmin()->create();

        $passwordHash = $user->getAuthPassword();

        $user->update(['password' => 'ChangedPassword123!']);

        $response = $this->withSession([
            Auth::guard('web')->getName() => $user->id,
            'password_hash_web' => $passwordHash,
        ])->getJson(route($route), ['Referer' => route('log-viewer.index')]);

        $response->assertUnauthorized();

        $this->assertGuest();
    }

    #[Test]
    #[TestWith(['horizon.index'])]
    #[TestWith(['log-viewer.index'])]
    #[TestWith(['log-viewer.hosts'])]
    public function blocked_superadmins_are_rejected(string $route): void
    {
        $user = User::factory()->superadmin()->blocked()->create();

        $response = $this->withSession([
            Auth::guard('web')->getName() => $user->id,
            'password_hash_web' => $user->getAuthPassword(),
        ])->getJson(route($route), ['Referer' => route('log-viewer.index')]);

        $response->assertForbidden();
    }

    #[Test]
    #[TestWith(['horizon.index'])]
    #[TestWith(['log-viewer.index'])]
    #[TestWith(['log-viewer.hosts'])]
    public function valid_superadmin_sessions_can_access_the_dashboards(string $route): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->withSession([
            Auth::guard('web')->getName() => $user->id,
            'password_hash_web' => $user->getAuthPassword(),
        ])->get(route($route), ['Referer' => route('log-viewer.index')]);

        $response->assertOk();
    }

    #[Test]
    #[TestWith(['horizon.index'])]
    #[TestWith(['log-viewer.index'])]
    #[TestWith(['log-viewer.hosts'])]
    public function guests_are_redirected_to_login(string $route): void
    {
        $response = $this->get(route($route), ['Referer' => route('log-viewer.index')]);

        $response->assertRedirect(route('login'));
    }

    #[Test]
    #[TestWith(['horizon.index'])]
    #[TestWith(['log-viewer.index'])]
    #[TestWith(['log-viewer.hosts'])]
    public function guest_json_requests_are_unauthorized(string $route): void
    {
        $response = $this->getJson(route($route), ['Referer' => route('log-viewer.index')]);

        $response->assertUnauthorized();
    }

    #[Test]
    #[TestWith(['horizon.index', RoleEnum::Admin])]
    #[TestWith(['horizon.index', RoleEnum::User])]
    #[TestWith(['log-viewer.index', RoleEnum::Admin])]
    #[TestWith(['log-viewer.index', RoleEnum::User])]
    #[TestWith(['log-viewer.hosts', RoleEnum::Admin])]
    #[TestWith(['log-viewer.hosts', RoleEnum::User])]
    public function non_superadmins_are_rejected(string $route, RoleEnum $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->withSession([
            Auth::guard('web')->getName() => $user->id,
            'password_hash_web' => $user->getAuthPassword(),
        ])->getJson(route($route), ['Referer' => route('log-viewer.index')]);

        $response->assertForbidden();
    }
}
