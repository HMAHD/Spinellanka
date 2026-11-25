<?php

declare(strict_types=1);

use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

test('two concurrent processes racing for identical slot serialize cleanly on shared database', function (): void {
    // Set up a temporary shared database file
    $dbPath = storage_path('framework/testing/concurrency_test_'.uniqid().'.sqlite');
    touch($dbPath);

    config(['database.connections.concurrency_test' => [
        'driver' => 'sqlite',
        'database' => $dbPath,
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => 10000,
    ]]);

    DB::connection('concurrency_test')->statement('PRAGMA journal_mode = WAL;');

    // Run migrations on the temporary database
    Artisan::call('migrate', [
        '--database' => 'concurrency_test',
        '--path' => 'database/migrations',
    ]);

    // Create a vehicle in this shared DB
    $vehicleId = DB::connection('concurrency_test')->table('vehicles')->insertGetId([
        'public_name' => 'Concurrency Van',
        'internal_fleet_id' => 'CONC-01',
        'make' => 'Toyota',
        'model' => 'KDH',
        'year' => 2024,
        'license_plate' => 'WP-CONC-001',
        'vehicle_type' => 'van',
        'passenger_capacity' => 7,
        'luggage_capacity' => 5,
        'transmission' => 'automatic',
        'has_ac' => true,
        'turnaround_buffer_minutes' => 60,
        'status' => 'active',
        'eligible_for_pickup' => true,
        'eligible_for_transfer' => true,
        'eligible_for_ride' => true,
        'eligible_for_rent' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $basePath = base_path();
    $scriptContent = <<<PHP
<?php
require '{$basePath}/vendor/autoload.php';
\$app = require_once '{$basePath}/bootstrap/app.php';
\$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();

\$dbPath = \$argv[1];
\$vehicleId = (int)\$argv[2];

config(['database.default' => 'sqlite']);
config(['database.connections.sqlite.database' => \$dbPath]);
config(['database.connections.sqlite.busy_timeout' => 10000]);
DB::purge('sqlite');
DB::reconnect('sqlite');
DB::statement('PRAGMA journal_mode = WAL;');

\$service = app(App\\Domain\\Availability\\Services\\AvailabilityService::class);
\$vehicle = App\\Models\\Vehicle::findOrFail(\$vehicleId);

\$start = Carbon\\Carbon::parse('2026-12-15 10:00:00', 'Asia/Colombo');
\$end = Carbon\\Carbon::parse('2026-12-15 14:00:00', 'Asia/Colombo');

try {
    \$service->createBlock(
        vehicle: \$vehicle,
        kind: App\\Domain\\Availability\\Enums\\ScheduleBlockKind::ConfirmedBooking,
        start: \$start,
        end: \$end,
        bufferBefore: 0,
        bufferAfter: 60
    );
    echo "SUCCESS\n";
    exit(0);
} catch (App\\Domain\\Availability\\Exceptions\\ScheduleConflictException \$e) {
    echo "CONFLICT\n";
    exit(2);
} catch (\\Throwable \$e) {
    echo "ERROR: " . \$e->getMessage() . "\n";
    exit(1);
}
PHP;

    $scriptPath = storage_path('framework/testing/worker_'.uniqid().'.php');
    file_put_contents($scriptPath, $scriptContent);

    // Launch both processes concurrently
    $proc1 = Process::start("php {$scriptPath} {$dbPath} {$vehicleId}");
    $proc2 = Process::start("php {$scriptPath} {$dbPath} {$vehicleId}");

    $result1 = $proc1->wait();
    $result2 = $proc2->wait();

    $output1 = trim($result1->output().' '.$result1->errorOutput());
    $output2 = trim($result2->output().' '.$result2->errorOutput());

    $exit1 = $result1->exitCode();
    $exit2 = $result2->exitCode();

    // Clean up temporary files
    @unlink($scriptPath);
    @unlink($dbPath);

    if ($exit1 !== 0 && $exit1 !== 2) {
        throw new RuntimeException("Process 1 failed with exit {$exit1}: {$output1}");
    }
    if ($exit2 !== 0 && $exit2 !== 2) {
        throw new RuntimeException("Process 2 failed with exit {$exit2}: {$output2}");
    }

    // Exactly one process must succeed (exit 0, SUCCESS) and one must detect conflict (exit 2, CONFLICT)
    $successes = ($exit1 === 0 ? 1 : 0) + ($exit2 === 0 ? 1 : 0);
    $conflicts = ($exit1 === 2 ? 1 : 0) + ($exit2 === 2 ? 1 : 0);

    expect($successes)->toBe(1)
        ->and($conflicts)->toBe(1);
});
