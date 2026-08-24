<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table): void {

            $table->id();

            $table->string('facility_name', 150);

            $table->text('description')->nullable();

            $table->string('location', 150)->nullable();

            $table->unsignedInteger('capacity');

            $table->string('status', 30)
                ->default('available')
                ->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
