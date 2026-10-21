<?php

use App\Rules\MinimumReservationDuration;
use Illuminate\Support\Facades\Validator;

test('reservations require two hours including at midnight', function ($start, $end, $valid) {
    $validator = Validator::make(['start_time' => $start, 'end_time' => $end], [
        'end_time' => ['required', new MinimumReservationDuration],
    ]);
    expect($validator->passes())->toBe($valid);
    if (! $valid) expect($validator->errors()->first('end_time'))->toBe('Reservations must be at least 2 hours.');
})->with([
    ['09:00', '09:30', false], ['09:00', '09:59', false],
    ['09:00', '10:00', false], ['09:00', '10:30', false],
    ['09:00', '10:59', false], ['09:00', '11:00', true],
    ['09:30', '11:30', true], ['22:00', '24:00', true], ['23:00', '24:00', false],
]);

test('event types contain exactly the fourteen requested categories without numbering', function () {
    expect(config('event-types'))->toBe([
        'Sports Activities', 'School Activities', 'Graduation and Recognition',
        'Pageant and Competitions', 'Seminars and Workshops', 'Fitness and Recreation',
        'Concert and Entertainment', 'Team Building Activities', 'Government and Public Services',
        'Health and Medical Activities', 'Community Activities', 'Employment Activities',
        'Religious Activities', 'Civic and Special Events',
    ]);
});
