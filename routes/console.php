<?php

use App\Services\BookingExpirationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:expire-stale', function (BookingExpirationService $expiration): void {
    $expired = $expiration->expireStale();
    $this->info("Expired {$expired} stale bookings.");
})->purpose('Expire bookings that passed their response or payment deadline');

Schedule::command('bookings:expire-stale')->everyFiveMinutes()->withoutOverlapping();
