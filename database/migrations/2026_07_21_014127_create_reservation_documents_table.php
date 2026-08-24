<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_documents', function (Blueprint $table): void {

            $table->id();

            $table->foreignId('reservation_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('document_type', 50);

            $table->string('original_filename');

            $table->string('stored_filename')->unique();

            $table->string('file_path');

            $table->string('mime_type', 100);

            $table->unsignedBigInteger('file_size');

            $table->timestamps();

            $table->index([
                'reservation_id',
                'document_type',
            ], 'reservation_documents_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_documents');
    }
};
