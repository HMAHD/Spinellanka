<?php
/**
 * scripts/deploy-webhook.php
 *
 * Secure HTTP-triggered deployment webhook for cPanel shared hosting
 * without shell access. GitHub Actions uploads the release tarball via
 * SFTP and then POSTs to this script to extract and activate it.
 *
 * This file is uploaded to public_html/ ONLY during deployment and
 * deleted immediately after. It is NOT part of the Laravel application.
 *
 * Security: requires a secret token in the Authorization header.
 */

declare(strict_types=1);

// ── Config ────────────────────────────────────────────────────────────────────
$TOKEN    = getenv('DEPLOY_WEBHOOK_SECRET') ?: ($_SERVER['DEPLOY_WEBHOOK_SECRET'] ?? '');
$BASE_DIR = '/home/spinellanka/spinellanka';
$PHP_BIN  = '/usr/local/bin/ea-php83';

// ── Auth ──────────────────────────────────────────────────────────────────────
header('Content-Type: text/plain; charset=utf-8');

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';
$provided   = str_replace('Bearer ', '', $authHeader);

if (empty($TOKEN) || !hash_equals($TOKEN, $provided)) {
    http_response_code(403);
    exit("Forbidden\n");
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function run(string $cmd): string
{
    $output = [];
    exec($cmd . ' 2>&1', $output, $code);
    $out = implode("\n", $output);
    echo "[" . ($code === 0 ? 'OK' : "FAIL:$code") . "] $cmd\n";
    if ($out) echo "    $out\n";
    if ($code !== 0) {
        http_response_code(500);
        exit("DEPLOY FAILED at: $cmd\nOutput: $out\n");
    }
    return $out;
}

// ── Main ──────────────────────────────────────────────────────────────────────
$releaseId = $_POST['release_id'] ?? date('YmdHis');
$archive   = "$BASE_DIR/.incoming/release.tar.gz";
$checksum  = "$BASE_DIR/.incoming/release.tar.gz.sha256";
$releaseDir = "$BASE_DIR/releases/$releaseId";
$currentLink = "$BASE_DIR/current";
$sharedEnv = "$BASE_DIR/shared/.env";
$sharedStorage = "$BASE_DIR/shared/storage";

echo "=== Spinellanka Deploy: $releaseId ===\n\n";

// 1. Verify archive exists
if (!file_exists($archive)) {
    http_response_code(422);
    exit("Archive not found: $archive\n");
}

// 2. Verify checksum
echo "-- Verifying checksum --\n";
run("cd " . escapeshellarg(dirname($archive)) . " && sha256sum -c release.tar.gz.sha256");

// 3. Create release directory and extract
echo "\n-- Extracting release --\n";
run("mkdir -p " . escapeshellarg($releaseDir));
run("tar -xzf " . escapeshellarg($archive) . " -C " . escapeshellarg($releaseDir));

// 4. Link shared .env
echo "\n-- Linking shared .env --\n";
run("ln -sf " . escapeshellarg($sharedEnv) . " " . escapeshellarg("$releaseDir/.env"));

// 5. Link shared storage
echo "\n-- Linking shared storage --\n";
run("rm -rf " . escapeshellarg("$releaseDir/storage"));
run("ln -sf " . escapeshellarg($sharedStorage) . " " . escapeshellarg("$releaseDir/storage"));
run("ln -sf " . escapeshellarg("$releaseDir/public") . " " . escapeshellarg("$BASE_DIR/../test.spinellanka.lk"));

// 6. Run migrations
echo "\n-- Running migrations --\n";
run("$PHP_BIN " . escapeshellarg("$releaseDir/artisan") . " migrate --force");

// 7. Seed roles/permissions (idempotent)
echo "\n-- Seeding roles & permissions --\n";
run("$PHP_BIN " . escapeshellarg("$releaseDir/artisan") . " db:seed --class=RoleAndPermissionSeeder --force");

// 8. Cache config/routes/views
echo "\n-- Caching framework --\n";
run("$PHP_BIN " . escapeshellarg("$releaseDir/artisan") . " config:cache");
run("$PHP_BIN " . escapeshellarg("$releaseDir/artisan") . " route:cache");
run("$PHP_BIN " . escapeshellarg("$releaseDir/artisan") . " view:cache");

// 9. Set permissions
echo "\n-- Setting permissions --\n";
run("chmod -R 755 " . escapeshellarg($releaseDir));
run("chmod -R 775 " . escapeshellarg("$releaseDir/bootstrap/cache"));

// 10. Atomic symlink swap
echo "\n-- Activating release --\n";
$tmpLink = "$currentLink.tmp.$$";
run("ln -sfn " . escapeshellarg($releaseDir) . " " . escapeshellarg($tmpLink));
run("mv -Tf " . escapeshellarg($tmpLink) . " " . escapeshellarg($currentLink));

// 11. Prune old releases (keep 2)
echo "\n-- Pruning old releases --\n";
$releases = glob("$BASE_DIR/releases/*", GLOB_ONLYDIR);
usort($releases, fn($a, $b) => strcmp($b, $a)); // newest first
foreach (array_slice($releases, 2) as $old) {
    run("rm -rf " . escapeshellarg($old));
}

// 12. Clean up incoming
run("rm -f " . escapeshellarg($archive) . " " . escapeshellarg($checksum));

echo "\n=== Deploy $releaseId complete ===\n";
echo "Live at: http://test.spinellanka.lk\n";
