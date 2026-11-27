<?php

/**
 * scripts/deploy-activate.php
 *
 * Cron-safe release activator for cPanel shared hosting without shell access
 * and without exposing an HTTP webhook in public_html.
 *
 * Uploaded to <DEPLOY_PATH>/shared/deploy-activate.php via SFTP and invoked
 * every minute by cPanel cron:
 *   * * * * * /usr/local/bin/ea-php83 /home/<user>/spinellanka/shared/deploy-activate.php >/dev/null 2>&1
 *
 * Exits cleanly in milliseconds when .incoming/deploy-request.json is absent.
 */

declare(strict_types=1);

$options = getopt('', ['base-dir::', 'php-bin::', 'skip-artisan']);

$baseDir = is_string($options['base-dir'] ?? null) && $options['base-dir'] !== ''
    ? rtrim($options['base-dir'], '/')
    : dirname(__DIR__);

$incomingDir = $baseDir.'/.incoming';
$requestFile = $incomingDir.'/deploy-request.json';

if (! is_file($requestFile)) {
    exit(0);
}

$lockFp = fopen($incomingDir.'/deploy.lock', 'c+');
if ($lockFp === false || ! flock($lockFp, LOCK_EX | LOCK_NB)) {
    exit(0);
}

$logLines = [];
$log = static function (string $line) use (&$logLines): void {
    $logLines[] = $line;
    fwrite(STDOUT, $line."\n");
};

$runCommand = static function (string $cmd) use (&$log): void {
    $output = [];
    $code = 0;
    exec($cmd.' 2>&1', $output, $code);
    $out = implode("\n", $output);
    $log('['.($code === 0 ? 'OK' : "FAIL:$code")."] $cmd");
    if ($out !== '') {
        foreach (explode("\n", $out) as $line) {
            $log("    $line");
        }
    }
    if ($code !== 0) {
        throw new RuntimeException("Command failed ($code): $cmd");
    }
};

$releaseId = 'unknown';

try {
    $rawRequest = file_get_contents($requestFile);
    $request = is_string($rawRequest) ? json_decode($rawRequest, true) : null;

    if (! is_array($request) || ! is_string($request['release_id'] ?? null)) {
        throw new RuntimeException('Invalid deploy-request.json payload.');
    }

    $releaseId = $request['release_id'];
    if (preg_match('/^[A-Za-z0-9._-]+$/', $releaseId) !== 1) {
        throw new RuntimeException("Invalid release_id format: $releaseId");
    }

    $phpBin = is_string($options['php-bin'] ?? null) && $options['php-bin'] !== ''
        ? $options['php-bin']
        : (is_string($request['php_bin'] ?? null) && $request['php_bin'] !== ''
            ? $request['php_bin']
            : '/usr/local/bin/ea-php83');

    $skipArtisan = array_key_exists('skip-artisan', $options);

    $archive = $incomingDir.'/release.tar.gz';
    $checksum = $incomingDir.'/release.tar.gz.sha256';
    $releasesDir = $baseDir.'/releases';
    $releaseDir = $releasesDir.'/'.$releaseId;
    $currentLink = $baseDir.'/current';
    $sharedEnv = $baseDir.'/shared/.env';
    $sharedStorage = $baseDir.'/shared/storage';

    $log("=== Spinellanka Deploy: $releaseId ===");

    if (! is_file($archive) || ! is_file($checksum)) {
        throw new RuntimeException('Release archive or checksum file is missing in .incoming/.');
    }

    $expectedLine = trim((string) file_get_contents($checksum));
    $parts = preg_split('/\s+/', $expectedLine);
    $expectedHash = strtolower((string) ($parts[0] ?? ''));
    if (preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1) {
        throw new RuntimeException('Checksum validation failed: malformed .sha256 file.');
    }

    $actualHash = strtolower((string) hash_file('sha256', $archive));
    if (! hash_equals($expectedHash, $actualHash)) {
        throw new RuntimeException("Checksum validation failed: expected $expectedHash, got $actualHash.");
    }
    $log("[OK] SHA-256 checksum verified ($actualHash)");

    if (! is_dir($releaseDir) && ! mkdir($releaseDir, 0755, true) && ! is_dir($releaseDir)) {
        throw new RuntimeException("Failed to create release directory: $releaseDir");
    }

    $runCommand('tar -xzf '.escapeshellarg($archive).' -C '.escapeshellarg($releaseDir));

    if (is_file($sharedEnv)) {
        $runCommand('ln -sf '.escapeshellarg($sharedEnv).' '.escapeshellarg("$releaseDir/.env"));
    }

    if (is_dir($sharedStorage)) {
        if (is_dir("$releaseDir/storage") && ! is_link("$releaseDir/storage")) {
            $runCommand('rm -rf '.escapeshellarg("$releaseDir/storage"));
        }
        $runCommand('ln -sf '.escapeshellarg($sharedStorage).' '.escapeshellarg("$releaseDir/storage"));
    }

    if (! $skipArtisan) {
        $artisan = escapeshellarg("$releaseDir/artisan");
        $phpQuoted = escapeshellarg($phpBin);
        $runCommand("$phpQuoted $artisan migrate --force");
        $runCommand("$phpQuoted $artisan db:seed --class=RoleAndPermissionSeeder --force");
        $runCommand("$phpQuoted $artisan config:cache");
        $runCommand("$phpQuoted $artisan route:cache");
        $runCommand("$phpQuoted $artisan view:cache");
    }

    $runCommand('chmod -R 755 '.escapeshellarg($releaseDir));
    if (is_dir("$releaseDir/bootstrap/cache")) {
        $runCommand('chmod -R 775 '.escapeshellarg("$releaseDir/bootstrap/cache"));
    }

    if (is_dir($currentLink) && ! is_link($currentLink)) {
        $runCommand('rm -rf '.escapeshellarg($currentLink));
    }

    $tmpLink = $currentLink.'.tmp.'.getmypid();
    $runCommand('ln -sfn '.escapeshellarg($releaseDir).' '.escapeshellarg($tmpLink));

    $mvOut = [];
    $mvCode = 0;
    exec('mv -Tf '.escapeshellarg($tmpLink).' '.escapeshellarg($currentLink).' 2>&1', $mvOut, $mvCode);
    if ($mvCode !== 0 && ! rename($tmpLink, $currentLink)) {
        throw new RuntimeException('Failed to activate current symlink.');
    }
    $log("[OK] Symlink activated: current -> $releaseDir");

    $releases = glob("$releasesDir/*", GLOB_ONLYDIR) ?: [];
    usort($releases, static fn (string $a, string $b): int => strcmp($b, $a));
    foreach (array_slice($releases, 2) as $oldRelease) {
        if (realpath($oldRelease) === realpath($releaseDir)) {
            continue;
        }
        $runCommand('rm -rf '.escapeshellarg($oldRelease));
    }

    @unlink($archive);
    @unlink($checksum);
    @unlink($requestFile);

    foreach (glob($incomingDir.'/deploy-result-*.json') ?: [] as $oldResult) {
        if (filemtime($oldResult) !== false && filemtime($oldResult) < time() - 86400) {
            @unlink($oldResult);
        }
    }

    $log("=== Deploy $releaseId complete ===");

    $resultPayload = json_encode([
        'status' => 'ok',
        'release_id' => $releaseId,
        'completed_at' => gmdate('c'),
        'log' => implode("\n", $logLines),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    file_put_contents($incomingDir."/deploy-result-$releaseId.json", (string) $resultPayload, LOCK_EX);
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(0);
} catch (Throwable $e) {
    $log('[ERROR] '.$e->getMessage());
    @rename($requestFile, $incomingDir.'/deploy-request.failed.json');

    $safeId = preg_match('/^[A-Za-z0-9._-]+$/', $releaseId) === 1 ? $releaseId : 'invalid';
    $resultPayload = json_encode([
        'status' => 'error',
        'release_id' => $releaseId,
        'completed_at' => gmdate('c'),
        'error' => $e->getMessage(),
        'log' => implode("\n", $logLines),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    file_put_contents($incomingDir."/deploy-result-$safeId.json", (string) $resultPayload, LOCK_EX);
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit(1);
}
