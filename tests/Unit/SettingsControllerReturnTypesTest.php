<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Settings\ConfirmedTwoFactorController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RecoveryCodesController;
use App\Http\Controllers\Settings\TwoFactorController;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SettingsControllerReturnTypesTest extends TestCase
{
    #[Test]
    public function settings_controller_actions_have_accurate_return_types(): void
    {
        $returnTypes = [
            ProfileController::class => [
                'edit' => View::class,
                'update' => RedirectResponse::class,
                'delete' => View::class,
                'destroy' => RedirectResponse::class,
            ],
            TwoFactorController::class => [
                'edit' => View::class,
                'update' => RedirectResponse::class,
                'destroy' => RedirectResponse::class,
            ],
            ConfirmedTwoFactorController::class => [
                'edit' => View::class.'|'.RedirectResponse::class,
                'update' => RedirectResponse::class,
            ],
            RecoveryCodesController::class => [
                'edit' => View::class.'|'.RedirectResponse::class,
                'update' => RedirectResponse::class,
            ],
        ];

        foreach ($returnTypes as $controller => $actions) {
            foreach ($actions as $action => $expectedReturnType) {
                $this->assertSame(
                    $expectedReturnType,
                    (string) (new ReflectionMethod($controller, $action))->getReturnType(),
                    "{$controller}::{$action}() has an unexpected return type.",
                );
            }
        }
    }
}
