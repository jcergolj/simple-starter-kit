<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoggingConfigurationTest extends TestCase
{
    #[Test]
    public function the_default_log_stack_resolves_without_bugsnag(): void
    {
        $this->assertSame('stack', config('logging.default'));
        $this->assertArrayNotHasKey('bugsnag', config('logging.channels'));

        config()->set('logging.channels.stack.channels', ['single']);

        Log::info('Logging configuration smoke test.');

        $this->assertFileExists(storage_path('logs/laravel.log'));
    }
}
