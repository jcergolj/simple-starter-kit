<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FilesystemConfigurationTest extends TestCase
{
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
}
