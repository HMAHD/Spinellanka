<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

class DeploymentLifecycleTest extends TestCase
{
    private string $tempBaseDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempBaseDir = sys_get_temp_dir().'/spinellanka_deploy_test_'.uniqid();
        mkdir($this->tempBaseDir, 0777, true);
        mkdir($this->tempBaseDir.'/shared', 0777, true);
        mkdir($this->tempBaseDir.'/shared/storage/app', 0777, true);
        mkdir($this->tempBaseDir.'/shared/storage/logs', 0777, true);
        mkdir($this->tempBaseDir.'/.incoming', 0777, true);
        file_put_contents($this->tempBaseDir.'/shared/.env', "APP_NAME=Spinellanka\nAPP_ENV=testing\nAPP_KEY=base64:".base64_encode(random_bytes(32))."\n");
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempBaseDir);
        parent::tearDown();
    }

    public function test_checksum_failure_aborts_deployment_before_extraction(): void
    {
        $projectRoot = base_path();
        $archive = $this->tempBaseDir.'/.incoming/release.tar.gz';
        $checksum = $this->tempBaseDir.'/.incoming/release.tar.gz.sha256';

        // Create corrupt archive and checksum
        file_put_contents($archive, 'dummy-tarball-content');
        file_put_contents($checksum, "wronghash00000000000000000000000000000000000000000000000000000000  release.tar.gz\n");

        $cmd = sprintf(
            'bash %s/scripts/deploy-release --base-dir %s --release-id 20261123000001 --skip-migrations 2>&1',
            escapeshellarg($projectRoot),
            escapeshellarg($this->tempBaseDir)
        );

        exec($cmd, $output, $exitCode);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Checksum validation failed', implode("\n", $output));

        // Assert target release directory was never extracted
        $this->assertDirectoryDoesNotExist($this->tempBaseDir.'/releases/20261123000001');
    }

    public function test_concurrency_lock_prevents_simultaneous_deployments(): void
    {
        $projectRoot = base_path();
        $lockDir = $this->tempBaseDir.'/deploy.lock.d';
        mkdir($lockDir, 0777, true);
        file_put_contents($lockDir.'/pid', (string) getmypid());

        // Create dummy archive and checksum
        $archive = $this->tempBaseDir.'/.incoming/release.tar.gz';
        $checksum = $this->tempBaseDir.'/.incoming/release.tar.gz.sha256';
        file_put_contents($archive, 'dummy');
        file_put_contents($checksum, hash('sha256', 'dummy')."  release.tar.gz\n");

        $cmd = sprintf(
            'bash %s/scripts/deploy-release --base-dir %s --release-id 20261123000002 --skip-migrations 2>&1',
            escapeshellarg($projectRoot),
            escapeshellarg($this->tempBaseDir)
        );

        exec($cmd, $output, $exitCode);
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Another deployment or rollback is actively holding the lock', implode("\n", $output));

        @unlink($lockDir.'/pid');
        @rmdir($lockDir);
    }

    public function test_atomic_activation_and_rollback_rehearsal(): void
    {
        $projectRoot = base_path();

        // 1. Package a real minimal test release
        $distDir = $this->tempBaseDir.'/dist';
        exec(sprintf(
            'bash %s/scripts/package-release --output-dir %s --skip-build 2>&1',
            escapeshellarg($projectRoot),
            escapeshellarg($distDir)
        ), $pkgOutput, $pkgCode);
        $this->assertSame(0, $pkgCode, implode("\n", $pkgOutput));

        $archive = $distDir.'/release.tar.gz';
        $checksum = $distDir.'/release.tar.gz.sha256';

        // 2. Deploy Release 1
        copy($archive, $this->tempBaseDir.'/.incoming/release.tar.gz');
        copy($checksum, $this->tempBaseDir.'/.incoming/release.tar.gz.sha256');

        $deploy1Cmd = sprintf(
            'bash %s/scripts/deploy-release --base-dir %s --release-id 20261123100001 --skip-migrations 2>&1',
            escapeshellarg($projectRoot),
            escapeshellarg($this->tempBaseDir)
        );
        exec($deploy1Cmd, $d1Out, $d1Code);
        $this->assertSame(0, $d1Code, implode("\n", $d1Out));

        $this->assertFileExists($this->tempBaseDir.'/current');
        $this->assertSame(
            realpath($this->tempBaseDir.'/releases/20261123100001'),
            realpath($this->tempBaseDir.'/current')
        );

        // Verify shared links
        $this->assertFileExists($this->tempBaseDir.'/current/.env');
        $this->assertFileExists($this->tempBaseDir.'/current/storage/app');
        $this->assertFileExists($this->tempBaseDir.'/current/storage/logs');
        $this->assertDirectoryExists($this->tempBaseDir.'/current/storage/framework/views');

        // 3. Deploy Release 2
        copy($archive, $this->tempBaseDir.'/.incoming/release.tar.gz');
        copy($checksum, $this->tempBaseDir.'/.incoming/release.tar.gz.sha256');

        $deploy2Cmd = sprintf(
            'bash %s/scripts/deploy-release --base-dir %s --release-id 20261123100002 --skip-migrations 2>&1',
            escapeshellarg($projectRoot),
            escapeshellarg($this->tempBaseDir)
        );
        exec($deploy2Cmd, $d2Out, $d2Code);
        $this->assertSame(0, $d2Code, implode("\n", $d2Out));

        clearstatcache(true);
        $this->assertSame(
            realpath($this->tempBaseDir.'/releases/20261123100002'),
            realpath($this->tempBaseDir.'/current')
        );

        // 4. Rehearse Rollback to Release 1
        $rollbackCmd = sprintf(
            'bash %s/scripts/rollback-release --base-dir %s 2>&1',
            escapeshellarg($projectRoot),
            escapeshellarg($this->tempBaseDir)
        );
        exec($rollbackCmd, $rbOut, $rbCode);
        $this->assertSame(0, $rbCode, implode("\n", $rbOut));

        clearstatcache(true);
        $this->assertSame(
            realpath($this->tempBaseDir.'/releases/20261123100001'),
            realpath($this->tempBaseDir.'/current')
        );
    }

    public function test_cron_activator_verifies_checksum_activates_release_and_writes_result(): void
    {
        $projectRoot = base_path();
        $distDir = $this->tempBaseDir.'/dist';

        exec(sprintf(
            'bash %s/scripts/package-release --output-dir %s --skip-build 2>&1',
            escapeshellarg($projectRoot),
            escapeshellarg($distDir)
        ), $pkgOutput, $pkgCode);
        $this->assertSame(0, $pkgCode, implode("\n", $pkgOutput));

        // 1. No-op cleanly when deploy-request.json does not exist
        exec(sprintf(
            '%s %s/scripts/deploy-activate.php --base-dir=%s --skip-artisan 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($projectRoot),
            escapeshellarg($this->tempBaseDir)
        ), $noopOut, $noopCode);
        $this->assertSame(0, $noopCode);

        // 2. Reject corrupt checksum and write error result
        file_put_contents($this->tempBaseDir.'/.incoming/release.tar.gz', 'corrupted');
        file_put_contents($this->tempBaseDir.'/.incoming/release.tar.gz.sha256', str_repeat('a', 64)."  release.tar.gz\n");
        file_put_contents($this->tempBaseDir.'/.incoming/deploy-request.json', json_encode(['release_id' => 'badchecksum001']));

        exec(sprintf(
            '%s %s/scripts/deploy-activate.php --base-dir=%s --skip-artisan 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($projectRoot),
            escapeshellarg($this->tempBaseDir)
        ), $failOut, $failCode);
        $this->assertSame(1, $failCode);
        $failResult = json_decode((string) file_get_contents($this->tempBaseDir.'/.incoming/deploy-result-badchecksum001.json'), true);
        $this->assertSame('error', $failResult['status']);
        $this->assertDirectoryDoesNotExist($this->tempBaseDir.'/releases/badchecksum001');

        // 3. Valid archive + checksum activates release cleanly
        copy($distDir.'/release.tar.gz', $this->tempBaseDir.'/.incoming/release.tar.gz');
        copy($distDir.'/release.tar.gz.sha256', $this->tempBaseDir.'/.incoming/release.tar.gz.sha256');
        file_put_contents($this->tempBaseDir.'/.incoming/deploy-request.json', json_encode(['release_id' => 'validrel000001']));

        exec(sprintf(
            '%s %s/scripts/deploy-activate.php --base-dir=%s --skip-artisan 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($projectRoot),
            escapeshellarg($this->tempBaseDir)
        ), $okOut, $okCode);
        $this->assertSame(0, $okCode, implode("\n", $okOut));

        $okResult = json_decode((string) file_get_contents($this->tempBaseDir.'/.incoming/deploy-result-validrel000001.json'), true);
        $this->assertSame('ok', $okResult['status']);
        $this->assertFileDoesNotExist($this->tempBaseDir.'/.incoming/deploy-request.json');
        $this->assertSame(
            realpath($this->tempBaseDir.'/releases/validrel000001'),
            realpath($this->tempBaseDir.'/current')
        );
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
