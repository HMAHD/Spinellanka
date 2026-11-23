<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

class ReleaseArtifactTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/spinellanka_test_art_'.uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function test_packages_immutable_artifact_with_valid_sha256_checksum(): void
    {
        $projectRoot = base_path();
        $outputDir = $this->tempDir.'/dist';

        // Run package-release with --skip-build because assets are already built in CI
        $cmd = sprintf(
            'bash %s/scripts/package-release --output-dir %s --skip-build 2>&1',
            escapeshellarg($projectRoot),
            escapeshellarg($outputDir)
        );

        exec($cmd, $output, $exitCode);
        $this->assertSame(0, $exitCode, implode("\n", $output));

        $archive = $outputDir.'/release.tar.gz';
        $checksumFile = $outputDir.'/release.tar.gz.sha256';

        $this->assertFileExists($archive);
        $this->assertFileExists($checksumFile);

        // Verify SHA256 matches
        $expectedChecksum = trim(explode(' ', file_get_contents($checksumFile))[0]);
        $actualChecksum = hash_file('sha256', $archive);
        $this->assertSame($expectedChecksum, $actualChecksum);

        // Verify that modifying the archive breaks checksum verification
        $corruptArchive = $this->tempDir.'/corrupt.tar.gz';
        copy($archive, $corruptArchive);
        file_put_contents($corruptArchive, 'corrupt', FILE_APPEND);
        $corruptChecksum = hash_file('sha256', $corruptArchive);
        $this->assertNotSame($expectedChecksum, $corruptChecksum);
    }

    public function test_release_artifact_enforces_strict_exclusions(): void
    {
        $projectRoot = base_path();
        $outputDir = $this->tempDir.'/dist';

        $cmd = sprintf(
            'bash %s/scripts/package-release --output-dir %s --skip-build 2>&1',
            escapeshellarg($projectRoot),
            escapeshellarg($outputDir)
        );
        exec($cmd, $output, $exitCode);
        $this->assertSame(0, $exitCode);

        // Inspect tarball contents via tar -tzf
        $archive = $outputDir.'/release.tar.gz';
        exec(sprintf('tar -tzf %s', escapeshellarg($archive)), $tarList, $tarCode);
        $this->assertSame(0, $tarCode);

        // Prohibited patterns
        foreach ($tarList as $entry) {
            $normalized = ltrim($entry, './');

            $this->assertFalse(str_starts_with($normalized, '.env'), "Artifact must not contain .env files: $entry");
            $this->assertFalse(str_starts_with($normalized, 'tests/'), "Artifact must not contain tests: $entry");
            $this->assertFalse(str_starts_with($normalized, '.git/'), "Artifact must not contain git history: $entry");
            $this->assertFalse(str_starts_with($normalized, '.github/'), "Artifact must not contain github workflows: $entry");
            $this->assertFalse(str_starts_with($normalized, 'node_modules/'), "Artifact must not contain node_modules: $entry");
            $this->assertFalse(str_contains($normalized, '.sqlite'), "Artifact must not contain sqlite databases: $entry");
            $this->assertFalse(in_array($normalized, ['phpunit.xml', 'phpstan.neon', 'vite.config.js', 'package.json']), "Artifact must not contain dev configs: $entry");
        }

        // Required production elements
        $tarContent = implode("\n", $tarList);
        $this->assertStringContainsString('artisan', $tarContent);
        $this->assertStringContainsString('composer.json', $tarContent);
        $this->assertStringContainsString('public/build/manifest.json', $tarContent);
        $this->assertStringContainsString('vendor/autoload.php', $tarContent);
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
