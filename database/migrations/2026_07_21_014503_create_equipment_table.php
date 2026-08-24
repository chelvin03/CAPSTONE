<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table): void {
            $table->id();

            $table->string('equipment_name', 150)->unique();
            $table->text('description')->nullable();

            $table->unsignedInteger('total_quantity');

            $table->string('unit', 50)
                ->default('piece');

            $table->string('status', 30)
                ->default('available')
                ->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
