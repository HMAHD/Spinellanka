<?php

use App\Domain\Booking\ServiceType;
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
