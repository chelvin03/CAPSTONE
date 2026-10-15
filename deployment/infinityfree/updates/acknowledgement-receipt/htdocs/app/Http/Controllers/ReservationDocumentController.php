<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\ReservationDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ReservationDocumentController extends Controller
{
    public function preview(Reservation $reservation, ReservationDocument $document)
    {
        abort_unless((int)$document->reservation_id === (int)$reservation->id, 404);
        $disk = Storage::disk(config('gym.documents.disk', 'local'));
        $root = realpath($disk->path(''));
        $path = realpath($disk->path($document->file_path));
        abort_unless($root && $path && str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR) && is_file($path), 404, 'The uploaded letter could not be found.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true), 415, 'This document type cannot be previewed.');
        $response = response()->file($path, [
            'Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "frame-ancestors 'self'",
        ]);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($document->stored_filename));
        return $response;
    }
}
