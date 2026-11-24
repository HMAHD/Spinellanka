<?php

use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Http\Controllers\AirportPickupBookingController;
use App\Http\Controllers\BookingStatusController;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('home', [
    'services' => ServiceType::cases(),
]))->name('home');

Route::get('/services', function (Request $request) {
    $service = ServiceType::tryFrom((string) $request->query('service'));

    abort_if($service === null, 404);

    return redirect()->route('services.show', ['service' => $service->value]);
})->name('services.choose');

Route::get('/services/{service}', function (string $service) {
    $serviceType = ServiceType::tryFrom($service);

    abort_if($serviceType === null, 404);

    return view('services.show', [
        'service' => $serviceType,
        'services' => ServiceType::cases(),
    ]);
})->name('services.show');

Route::prefix('booking')->name('booking.')->group(function (): void {
    Route::get('/airport-pickup', [AirportPickupBookingController::class, 'create'])->name('airport-pickup');
    Route::post('/airport-pickup/search', [AirportPickupBookingController::class, 'search'])->name('airport-pickup.search');
    Route::post('/airport-pickup/hold', [AirportPickupBookingController::class, 'hold'])->name('airport-pickup.hold');
    Route::post('/airport-pickup/submit', [AirportPickupBookingController::class, 'submit'])->name('airport-pickup.submit');
    Route::get('/{reference}/{token}', [BookingStatusController::class, 'show'])->name('status');
});

Route::get('/fleet', function () {
    $vehicles = Vehicle::query()
        ->where('status', VehicleStatus::Active)
        ->with('publicApprovedMedia')
        ->orderBy('id')
        ->get();

    return view('fleet.index', [
        'vehicles' => $vehicles,
    ]);
})->name('fleet.index');

Route::get('/fleet/{vehicle}', function (Vehicle $vehicle) {
    abort_if($vehicle->status !== VehicleStatus::Active, 404);

    $vehicle->load('publicApprovedMedia');

    return view('fleet.show', [
        'vehicle' => $vehicle,
    ]);
})->name('fleet.show');

Route::view('/how-it-works', 'how-it-works')->name('how-it-works');

Route::view('/help', 'help')->name('help');
Route::redirect('/faq', '/help', 301);

Route::view('/contact', 'contact')->name('contact');

Route::view('/legal/terms', 'legal.terms')->name('legal.terms');
Route::view('/legal/privacy', 'legal.privacy')->name('legal.privacy');
