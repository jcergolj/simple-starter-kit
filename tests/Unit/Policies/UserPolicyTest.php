<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\UserPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(UserPolicy::class)]
class UserPolicyTest extends TestCase
{
    #[Test]
    public function admin_can_manage_regular_users(): void
    {
        $admin = User::factory()->admin()->make();
        $user = User::factory()->make();
        $policy = new UserPolicy;

        $this->assertTrue($policy->update($admin, $user));

        $this->assertTrue($policy->delete($admin, $user));

        $this->assertTrue($policy->block($admin, $user));
    }

    #[Test]
    public function admin_cannot_manage_admins_or_themselves(): void
    {
        $admin = User::factory()->admin()->make();
        $otherAdmin = User::factory()->admin()->make();
        $policy = new UserPolicy;

        $this->assertFalse($policy->update($admin, $admin));

        $this->assertFalse($policy->delete($admin, $otherAdmin));

        $this->assertFalse($policy->block($admin, $otherAdmin));
    }
}
