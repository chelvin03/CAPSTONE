<?php

use App\Services\ReservationReferenceService;
use Illuminate\Support\Facades\DB;

test('reservation references follow submission order across letter boundaries', function () {
    $service = app(ReservationReferenceService::class);
    expect($service->next())->toBe('R1-A')
        ->and($service->next())->toBe('R2-A');

    DB::table('reservation_reference_sequences')->where('id', 1)->update(['last_number' => 99]);
    expect($service->next())->toBe('R100-A')
        ->and($service->next())->toBe('R1-B');

    DB::table('reservation_reference_sequences')->where('id', 1)->update(['last_number' => 2599]);
    expect($service->next())->toBe('R100-Z')
        ->and($service->next())->toBe('R1-AA');
});

test('failed booking transactions do not consume a reference number', function () {
    try {
        DB::transaction(function () {
            expect(app(ReservationReferenceService::class)->next())->toBe('R1-A');
            throw new RuntimeException('Booking failed');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Booking failed');
    }

    expect(app(ReservationReferenceService::class)->next())->toBe('R1-A');
});
