<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_records', function (Blueprint $table): void {
            $table->id();
            $table->string('filename')->unique();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 64);
            $table->unsignedInteger('table_count')->default(0);
            $table->unsignedBigInteger('row_count')->default(0);
            $table->unsignedInteger('file_count')->default(0);
            $table->string('status', 30)->default('ready')->index();
            $table->string('type', 30)->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_records');
    }
};
