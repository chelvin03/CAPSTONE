<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'reservation_status_histories',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('reservation_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId('changed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('previous_status', 30)->nullable();
                $table->string('new_status', 30);

                $table->text('remarks')->nullable();

                $table->timestamps();

                $table->index(
                    ['reservation_id', 'created_at'],
                    'reservation_status_history_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_status_histories');
    }
};
