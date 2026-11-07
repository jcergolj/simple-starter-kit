<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\PhpseclibV3\UnableToAuthenticate;
use League\Flysystem\PhpseclibV3\UnableToEstablishAuthenticityOfHost;
use League\Flysystem\UnableToCheckFileExistence;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SftpBackupTest extends TestCase
{
    private ?Process $server = null;

    private ?TemporaryDirectory $directory = null;

    private string $fingerprint;

    protected function setUp(): void
    {
        parent::setUp();

        $finder = new ExecutableFinder;

        $sshd = $finder->find('sshd');

        $keygen = $finder->find('ssh-keygen');

        if ($sshd === null || $keygen === null) {
            $this->markTestSkipped('The isolated SFTP identity test requires OpenSSH sshd and ssh-keygen.');
        }

        $this->directory = (new TemporaryDirectory(storage_path('framework/testing')))->create();

        $hostKey = $this->directory->path('host-key.pem');

        (new Process([$keygen, '-q', '-t', 'ed25519', '-N', '', '-f', $hostKey]))->mustRun();

        $keyInfo = (new Process([$keygen, '-lf', $hostKey.'.pub', '-E', 'sha256']))->mustRun()->getOutput();

        $this->fingerprint = explode(' ', trim($keyInfo))[1];

        $listener = stream_socket_server('tcp://127.0.0.1:0');

        $address = stream_socket_get_name($listener, false);

        $port = (int) parse_url('tcp://'.$address, PHP_URL_PORT);

        fclose($listener);

        $configuration = $this->directory->path('sshd.conf');

        file_put_contents($configuration, implode(PHP_EOL, [
            'ListenAddress 127.0.0.1',
            'Port '.$port,
            'HostKey '.$hostKey,
            'PidFile '.$this->directory->path('sshd.pid'),
            'UsePAM no',
            'PasswordAuthentication yes',
            'PubkeyAuthentication no',
            'DenyUsers *',
            'Subsystem sftp internal-sftp',
            '',
        ]));

        $this->server = new Process([$sshd, '-D', '-e', '-f', $configuration]);
        $this->server->setTimeout(10);
        $this->server->start();

        $ready = $this->server->waitUntil(function (string $type, string $output): bool {
            return str_contains($output, 'Server listening on');
        });

        $this->assertTrue($ready, $this->server->getErrorOutput());

        config([
            'filesystems.disks.sftp-backup.host' => '127.0.0.1',
            'filesystems.disks.sftp-backup.port' => $port,
            'filesystems.disks.sftp-backup.username' => 'opencode-backup-test',
            'filesystems.disks.sftp-backup.password' => 'not-a-real-password',
            'filesystems.disks.sftp-backup.privateKey' => null,
            'filesystems.disks.sftp-backup.passphrase' => null,
            'filesystems.disks.sftp-backup.maxTries' => 0,
            'filesystems.disks.sftp-backup.timeout' => 3,
        ]);
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
        $this->directory?->delete();

        parent::tearDown();
    }

    /**
     * The local server denies every account, so a matching identity must reach
     * authentication while an impostor must be rejected before credentials are used.
     */
    #[Test]
    #[TestWith([true, UnableToAuthenticate::class])]
    #[TestWith([false, UnableToEstablishAuthenticityOfHost::class])]
    public function host_identity_is_checked_before_authentication(bool $matchingFingerprint, string $expectedException): void
    {
        config()->set('filesystems.disks.sftp-backup.hostFingerprint', $matchingFingerprint
            ? $this->fingerprint
            : 'SHA256:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA');

        try {
            Storage::disk('sftp-backup')->exists('probe');
        } catch (UnableToCheckFileExistence $exception) {
            $this->assertInstanceOf($expectedException, $exception->getPrevious());

            return;
        }

        $this->fail('The isolated server must reject the probe without authenticating or transferring backups.');
    }
}
