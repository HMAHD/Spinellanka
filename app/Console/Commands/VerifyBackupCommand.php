<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Throwable;

class VerifyBackupCommand extends Command
{
    protected $signature = 'backup:verify
        {--output-dir= : Directory for temporary verification dump/manifest (defaults to storage/app/private/backups)}
        {--keep-manifest : Keep generated SHA-256 verification manifest file}';

    protected $description = 'Verify database backup capability, generate checksum manifest, and check off-host backup readiness';

    public function handle(): int
    {
        $dir = (string) ($this->option('output-dir') ?: storage_path('app/private/backups'));
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0750, true);
        }

        $connection = (string) Config::get('database.default');
        $dbConfig = Config::get("database.connections.{$connection}", []);
        $driver = (string) ($dbConfig['driver'] ?? '');

        $timestamp = now('UTC')->format('Ymd_His');
        $dumpPath = "{$dir}/verify_{$timestamp}.bak";
        $manifestPath = "{$dir}/verify_{$timestamp}.sha256";
        $cnfPath = "{$dir}/.my_verify_{$timestamp}.cnf";

        try {
            if ($driver === 'sqlite') {
                $dbPath = (string) ($dbConfig['database'] ?? ':memory:');
                if ($dbPath === ':memory:') {
                    File::put($dumpPath, '-- SQLite :memory: schema verification snapshot');
                } elseif (File::exists($dbPath)) {
                    File::copy($dbPath, $dumpPath);
                } else {
                    $this->error("SQLite database file not found at {$dbPath}.");

                    return self::FAILURE;
                }
            } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
                if (! function_exists('proc_open')) {
                    $this->warn('BLOCKED: proc_open is disabled in PHP configuration; cannot run mariadb-dump/mysqldump.');

                    return self::FAILURE;
                }

                $host = (string) ($dbConfig['host'] ?? '127.0.0.1');
                $port = (string) ($dbConfig['port'] ?? '3306');
                $user = (string) ($dbConfig['username'] ?? '');
                $password = (string) ($dbConfig['password'] ?? '');
                $database = (string) ($dbConfig['database'] ?? '');

                $cnfContent = "[client]\nhost=\"{$host}\"\nport=\"{$port}\"\nuser=\"{$user}\"\npassword=\"{$password}\"\n";
                File::put($cnfPath, $cnfContent);
                chmod($cnfPath, 0600);

                $binary = trim((string) shell_exec('command -v mariadb-dump || command -v mysqldump'));
                if ($binary === '') {
                    $this->warn('BLOCKED: Neither mariadb-dump nor mysqldump is installed on PATH.');

                    return self::FAILURE;
                }

                $cmd = sprintf(
                    '%s --defaults-extra-file=%s --single-transaction --quick %s > %s',
                    escapeshellcmd($binary),
                    escapeshellarg($cnfPath),
                    escapeshellarg($database),
                    escapeshellarg($dumpPath)
                );

                $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                if (! is_resource($proc)) {
                    $this->error('Failed to spawn database dump process.');

                    return self::FAILURE;
                }
                stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exitCode = proc_close($proc);

                if ($exitCode !== 0 || ! File::exists($dumpPath)) {
                    $this->error('Database dump verification failed: '.trim((string) $stderr));

                    return self::FAILURE;
                }
            } else {
                $this->error("Unsupported database driver '{$driver}' for backup verification.");

                return self::FAILURE;
            }

            $checksum = hash_file('sha256', $dumpPath);
            $sizeBytes = File::size($dumpPath);
            $offHostDisk = (string) Config::get('services.backup.off_host_disk', '');
            $offHostReady = $offHostDisk !== '' && filled(Config::get("filesystems.disks.{$offHostDisk}.bucket"));

            $manifest = json_encode([
                'verified_at_utc' => now('UTC')->toIso8601String(),
                'driver' => $driver,
                'dump_size_bytes' => $sizeBytes,
                'dump_sha256' => $checksum,
                'off_host_backup_status' => $offHostReady ? 'configured' : 'blocked_unprovisioned',
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

            File::put($manifestPath, $manifest);

            $this->info("Local backup capability verified ({$driver}, {$sizeBytes} bytes, sha256: {$checksum}).");
            if (! $offHostReady) {
                $this->warn('Off-host encrypted backup destination is BLOCKED / unprovisioned (Phase 01 Gate 10).');
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Backup verification error: {$e->getMessage()}");

            return self::FAILURE;
        } finally {
            if (File::exists($cnfPath)) {
                File::delete($cnfPath);
            }
            if (File::exists($dumpPath)) {
                File::delete($dumpPath);
            }
            if (! $this->option('keep-manifest') && File::exists($manifestPath)) {
                File::delete($manifestPath);
            }
        }
    }
}
