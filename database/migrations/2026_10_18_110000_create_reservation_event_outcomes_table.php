<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservation_event_outcomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('outcome', 30);
            $table->unsignedInteger('actual_attendees');
            $table->unsignedInteger('expected_attendees');
            $table->json('schedule');
            $table->text('remarks');
            $table->foreignId('recorded_by')->constrained('users');
            $table->timestamp('recorded_at');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_event_outcomes');
    }
};
