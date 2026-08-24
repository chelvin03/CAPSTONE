<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_equipment', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('reservation_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('equipment_id')
                ->constrained('equipment')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity_requested');

            $table->unsignedInteger('quantity_approved')
                ->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique(
                ['reservation_id', 'equipment_id'],
                'reservation_equipment_unique'
            );

            $table->index(
                ['equipment_id', 'reservation_id'],
                'reservation_equipment_lookup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_equipment');
    }
};
