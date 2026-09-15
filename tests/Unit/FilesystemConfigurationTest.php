<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Env;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FilesystemConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Env::enablePutenv();
    }

    protected function tearDown(): void
    {
        Env::disablePutenv();

        parent::tearDown();
    }

    #[Test]
    public function sftp_root_uses_the_configured_environment_value(): void
    {
        putenv('SFTP_ROOT=/srv/backups/custom');

        $filesystems = require base_path('config/filesystems.php');

        $this->assertSame(
            '/srv/backups/custom',
            $filesystems['disks']['sftp-backup']['root'],
        );
    }

    #[Test]
    public function sftp_root_defaults_to_the_backup_directory(): void
    {
        putenv('SFTP_ROOT');

        $filesystems = require base_path('config/filesystems.php');

        $this->assertSame('/home/backup', $filesystems['disks']['sftp-backup']['root']);
    }

    #[Test]
    public function sftp_host_fingerprint_uses_the_configured_environment_value(): void
    {
        putenv('SFTP_HOST_FINGERPRINT=SHA256:known-host-key');

        $filesystems = require base_path('config/filesystems.php');

        $this->assertSame(
            'SHA256:known-host-key',
            $filesystems['disks']['sftp-backup']['hostFingerprint'],
        );
    }

    #[Test]
    public function sftp_host_fingerprint_is_unset_by_default(): void
    {
        putenv('SFTP_HOST_FINGERPRINT');

        $filesystems = require base_path('config/filesystems.php');

        $this->assertNull($filesystems['disks']['sftp-backup']['hostFingerprint']);
    }
}
