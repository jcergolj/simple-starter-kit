<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FilesystemConfigurationTest extends TestCase
{
    #[Test]
    public function s3_disk_resolves_without_contacting_aws(): void
    {
        config([
            'filesystems.disks.s3.key' => 'test-key',
            'filesystems.disks.s3.secret' => 'test-secret',
            'filesystems.disks.s3.region' => 'us-east-1',
            'filesystems.disks.s3.bucket' => 'test-bucket',
            'filesystems.disks.s3.http_handler' => function (): never {
                $this->fail('Resolving the S3 disk must not contact AWS.');
            },
        ]);

        $disk = Storage::disk('s3');

        $this->assertInstanceOf(AwsS3V3Adapter::class, $disk);
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
