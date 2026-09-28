<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRecord extends Model
{
    protected $fillable = [
        'filename', 'disk', 'path', 'size_bytes', 'checksum', 'table_count',
        'row_count', 'file_count', 'status', 'type', 'created_by',
        'verified_at', 'restored_at', 'last_error',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'table_count' => 'integer',
        'row_count' => 'integer',
        'file_count' => 'integer',
        'verified_at' => 'datetime',
        'restored_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
