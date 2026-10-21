<?php

function exerciseReservationEntryDiagnostic(string $method, string $uri, bool $writable = true): ?array
{
    $directory = sys_get_temp_dir().'/mcst-entry-diagnostic-'.uniqid();
    mkdir($directory);
    mkdir($directory.'/public');
    if ($writable) {
        mkdir($directory.'/storage');
        mkdir($directory.'/storage/logs');
    }
    $originalServer = $_SERVER;
    try {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_COOKIE'] = 'private-session-value';
        $source = file_get_contents(dirname(__DIR__, 2).'/public/index.php');
        preg_match('~// BEGIN temporary reservation entry diagnostic.*?\n(.*?)// END temporary reservation entry diagnostic\.~s', $source, $match);
        if (! isset($match[1])) throw new RuntimeException('Entry diagnostic was not found.');
        eval(str_replace('__DIR__', var_export($directory.'/public', true), $match[1]));
        $log = $directory.'/storage/logs/reservation-entry.log';
        return is_file($log) ? json_decode(file_get_contents($log), true, flags: JSON_THROW_ON_ERROR) : null;
    } finally {
        $_SERVER = $originalServer;
        if (is_file($directory.'/storage/logs/reservation-entry.log')) unlink($directory.'/storage/logs/reservation-entry.log');
        if ($writable) {
            rmdir($directory.'/storage/logs');
            rmdir($directory.'/storage');
        }
        rmdir($directory.'/public');
        rmdir($directory);
    }
}

test('entry trace records a submission before framework startup without cookies or query details', function () {
    $row = exerciseReservationEntryDiagnostic('POST', '/reserve?email=private@example.com');
    expect($row['method'])->toBe('POST')->and($row['path'])->toBe('/reserve')->and($row['stage'])->toBe('php_entry')
        ->and($row['diagnostic_id'])->toMatch('/^[a-f0-9]{16}$/')
        ->and(json_encode($row))->not->toContain('private@example.com', 'private-session-value');
    expect(array_keys($row))->toBe(['time_utc', 'diagnostic_id', 'stage', 'method', 'path']);
});

test('entry trace provides a GET control while ignoring unrelated requests', function () {
    expect(exerciseReservationEntryDiagnostic('GET', '/reserve/request?i=1')['method'])->toBe('GET')
        ->and(exerciseReservationEntryDiagnostic('POST', '/reserve/edit-details'))->toBeNull()
        ->and(exerciseReservationEntryDiagnostic('GET', '/admin/dashboard'))->toBeNull();
});

test('entry trace allows request handling to continue when the log directory is unavailable', function () {
    expect(exerciseReservationEntryDiagnostic('POST', '/reserve', false))->toBeNull();
});
