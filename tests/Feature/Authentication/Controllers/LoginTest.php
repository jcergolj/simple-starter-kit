<?php

declare(strict_types=1);

namespace Tests\Feature\Authentication\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_user_can_log_in_with_a_mixed_case_email(): void
    {
        User::factory()->create([
            'email' => 'alice@example.com',
            'password' => 'password',
        ]);

        $this->post(route('login'), [
            'email' => 'ALICE@EXAMPLE.COM',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }
}
