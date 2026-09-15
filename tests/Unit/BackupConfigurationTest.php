<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Env;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackupConfigurationTest extends TestCase
{
    #[Test]
    public function database_backup_uses_the_configured_mysql_connection(): void
    {
        $originalEnvironment = $this->databaseConnectionEnvironment();
        $this->setDatabaseConnectionEnvironment('mysql');
        Env::enablePutenv();

        try {
            $backup = require base_path('config/backup.php');
        } finally {
            $this->restoreDatabaseConnectionEnvironment($originalEnvironment);
            Env::disablePutenv();
        }

        $this->assertSame(['mysql'], $backup['backup']['source']['databases']);
    }

    #[Test]
    public function database_backup_defaults_to_sqlite(): void
    {
        $originalEnvironment = $this->databaseConnectionEnvironment();
        $this->setDatabaseConnectionEnvironment(false);
        Env::enablePutenv();

        try {
            $backup = require base_path('config/backup.php');
        } finally {
            $this->restoreDatabaseConnectionEnvironment($originalEnvironment);
            Env::disablePutenv();
        }

        $this->assertSame(['sqlite'], $backup['backup']['source']['databases']);
    }

    /** @return array{env: string|null, server: string|null, process: string|false} */
    private function databaseConnectionEnvironment(): array
    {
        return [
            'env' => $_ENV['DB_CONNECTION'] ?? null,
            'server' => $_SERVER['DB_CONNECTION'] ?? null,
            'process' => getenv('DB_CONNECTION'),
        ];
    }

    private function setDatabaseConnectionEnvironment(string|false $connection): void
    {
        if ($connection === false) {
            unset($_ENV['DB_CONNECTION'], $_SERVER['DB_CONNECTION']);
            putenv('DB_CONNECTION');

            return;
        }

        putenv('DB_CONNECTION='.$connection);
        $_ENV['DB_CONNECTION'] = $connection;
        $_SERVER['DB_CONNECTION'] = $connection;
    }

    /** @param  array{env: string|null, server: string|null, process: string|false}  $environment */
    private function restoreDatabaseConnectionEnvironment(array $environment): void
    {
        if ($environment['env'] === null) {
            unset($_ENV['DB_CONNECTION']);
        } else {
            $_ENV['DB_CONNECTION'] = $environment['env'];
        }

        if ($environment['server'] === null) {
            unset($_SERVER['DB_CONNECTION']);
        } else {
            $_SERVER['DB_CONNECTION'] = $environment['server'];
        }

        if ($environment['process'] === false) {
            putenv('DB_CONNECTION');

            return;
        }

        putenv('DB_CONNECTION='.$environment['process']);
    }
}
