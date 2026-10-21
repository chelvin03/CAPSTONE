<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('schedule_proposals', fn (Blueprint $table) => $table->string('kind', 30)->default('replacement'));
        Schema::create('equipment_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->restrictOnDelete();
            $table->unsignedInteger('issued');
            $table->unsignedInteger('returned_good');
            $table->unsignedInteger('damaged');
            $table->unsignedInteger('missing');
            $table->unsignedInteger('restored')->default(0);
            $table->text('remarks');
            $table->foreignId('recorded_by')->constrained('users');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['reservation_id', 'equipment_id']);
        });
        Schema::create('reservation_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('recipient');
            $table->string('status', 20)->default('sending');
            $table->unsignedInteger('attempts')->default(1);
            $table->timestamp('attempted_at');
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_reminders');
        Schema::dropIfExists('equipment_returns');
        Schema::table('schedule_proposals', fn (Blueprint $table) => $table->dropColumn('kind'));
    }
};
