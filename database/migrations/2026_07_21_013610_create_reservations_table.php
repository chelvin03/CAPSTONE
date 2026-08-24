<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();

            $table->string('reference_number', 50)->unique();

            $table->foreignId('user_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('facility_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('reservation_type', 50)->index();

            $table->string('event_name');
            $table->string('event_type', 100)->nullable();
            $table->text('purpose');

            $table->string('contact_person');
            $table->string('contact_number', 30);

            $table->unsignedInteger('expected_attendees');

            $table->date('reservation_date')->index();

            $table->time('start_time');
            $table->time('end_time');
            $table->time('setup_time')->nullable();
            $table->time('cleanup_time')->nullable();

            $table->string('status', 30)
                ->default('new')
                ->index();

            $table->unsignedInteger('priority_number')->nullable();

            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();

            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('rejected_at')->nullable();

            $table->foreignId('cancelled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(
                ['facility_id', 'reservation_date', 'start_time', 'end_time'],
                'reservations_schedule_index'
            );

            $table->index(
                ['reservation_date', 'status'],
                'reservations_date_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
