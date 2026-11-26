<?php

use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Http\Controllers\AirportPickupBookingController;
use App\Http\Controllers\AirportTransferBookingController;
use App\Http\Controllers\BookingStatusController;
use App\Http\Controllers\DataExportDownloadController;
use App\Http\Controllers\DriverTripController;
use App\Http\Controllers\FinancialDocumentController;
use App\Http\Controllers\PaymentCheckoutController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PrivateMediaDownloadController;
use App\Http\Controllers\RentBookingController;
use App\Http\Controllers\RideBookingController;
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

Route::prefix('booking')->name('booking.')->middleware('throttle:60,1')->group(function (): void {
    // Airport Pickup
    Route::get('/airport-pickup', [AirportPickupBookingController::class, 'create'])->name('airport-pickup');
    Route::post('/airport-pickup/search', [AirportPickupBookingController::class, 'search'])->name('airport-pickup.search');
    Route::get('/airport-pickup/vehicles', [AirportPickupBookingController::class, 'vehicles'])->name('airport-pickup.vehicles');
    Route::post('/airport-pickup/hold', [AirportPickupBookingController::class, 'hold'])->name('airport-pickup.hold');
    Route::get('/airport-pickup/contact', [AirportPickupBookingController::class, 'contact'])->name('airport-pickup.contact');
    Route::post('/airport-pickup/submit', [AirportPickupBookingController::class, 'submit'])->middleware('throttle:10,1')->name('airport-pickup.submit');

    // Airport Transfer
    Route::get('/airport-transfer', [AirportTransferBookingController::class, 'create'])->name('airport-transfer');
    Route::post('/airport-transfer/search', [AirportTransferBookingController::class, 'search'])->name('airport-transfer.search');
    Route::get('/airport-transfer/vehicles', [AirportTransferBookingController::class, 'vehicles'])->name('airport-transfer.vehicles');
    Route::post('/airport-transfer/hold', [AirportTransferBookingController::class, 'hold'])->name('airport-transfer.hold');
    Route::get('/airport-transfer/contact', [AirportTransferBookingController::class, 'contact'])->name('airport-transfer.contact');
    Route::post('/airport-transfer/submit', [AirportTransferBookingController::class, 'submit'])->middleware('throttle:10,1')->name('airport-transfer.submit');

    // Ride (Point-to-point / Multi-stop chauffeured journey)
    Route::get('/ride', [RideBookingController::class, 'create'])->name('ride');
    Route::post('/ride/search', [RideBookingController::class, 'search'])->name('ride.search');
    Route::get('/ride/vehicles', [RideBookingController::class, 'vehicles'])->name('ride.vehicles');
    Route::post('/ride/hold', [RideBookingController::class, 'hold'])->name('ride.hold');
    Route::get('/ride/contact', [RideBookingController::class, 'contact'])->name('ride.contact');
    Route::post('/ride/submit', [RideBookingController::class, 'submit'])->middleware('throttle:10,1')->name('ride.submit');

    // Rent (Self-Drive)
    Route::get('/rent', [RentBookingController::class, 'create'])->name('rent');
    Route::post('/rent/search', [RentBookingController::class, 'search'])->name('rent.search');
    Route::get('/rent/vehicles', [RentBookingController::class, 'vehicles'])->name('rent.vehicles');
    Route::post('/rent/hold', [RentBookingController::class, 'hold'])->name('rent.hold');
    Route::get('/rent/contact', [RentBookingController::class, 'contact'])->name('rent.contact');
    Route::post('/rent/submit', [RentBookingController::class, 'submit'])->middleware('throttle:10,1')->name('rent.submit');

    Route::get('/{reference}/{token}', [BookingStatusController::class, 'show'])->name('status');
    Route::post('/{reference}/{token}/accept', [BookingStatusController::class, 'accept'])->middleware('throttle:15,1')->name('status.accept');
    Route::post('/{reference}/{token}/decline', [BookingStatusController::class, 'decline'])->middleware('throttle:15,1')->name('status.decline');
    Route::post('/{reference}/{token}/change', [BookingStatusController::class, 'change'])->middleware('throttle:15,1')->name('status.change');
    Route::post('/{reference}/{token}/cancel', [BookingStatusController::class, 'cancel'])->middleware('throttle:15,1')->name('status.cancel');
    Route::post('/{reference}/{token}/extension', [BookingStatusController::class, 'extension'])->middleware('throttle:15,1')->name('status.extension');

    // Payment checkout & return
    Route::post('/{reference}/{token}/payment/checkout', [PaymentCheckoutController::class, 'checkout'])->middleware('throttle:15,1')->name('payment.checkout');
    Route::get('/{reference}/{token}/payment/return', [PaymentCheckoutController::class, 'return'])->name('payment.return');
    Route::get('/{reference}/{token}/payment/cancel', [PaymentCheckoutController::class, 'cancel'])->name('payment.cancel');

    // Financial document download
    Route::get('/{reference}/{token}/documents/{documentNumber}', [FinancialDocumentController::class, 'download'])->name('documents.download');
});

// Asynchronous payment gateway webhook
Route::post('/webhooks/payment/{provider}', [PaymentWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhooks.payment');

// Staff financial document view route
Route::get('/staff/bookings/{booking}/documents/{documentNumber}', [FinancialDocumentController::class, 'staffView'])
    ->middleware(['auth'])
    ->name('staff.documents.view');

// Operations private attachments & data export downloads
Route::middleware(['auth'])->prefix('/operations')->name('operations.')->group(function (): void {
    Route::get('/attachments/{uuid}', [PrivateMediaDownloadController::class, 'download'])->name('attachments.download');
    Route::get('/exports/{uuid}', [DataExportDownloadController::class, 'download'])->name('exports.download');
});

// Driver Mobile Trip Routes (Stateless, token-authorized)
Route::prefix('/driver/trip/{token}')->name('driver.trip.')->group(function (): void {
    Route::get('/', [DriverTripController::class, 'show'])->name('show');
    Route::post('/status', [DriverTripController::class, 'updateStatus'])->middleware('throttle:30,1')->name('status');
    Route::post('/issue', [DriverTripController::class, 'reportIssue'])->middleware('throttle:15,1')->name('issue');
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
