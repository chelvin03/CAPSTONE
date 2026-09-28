<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->string('report_type', 20);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('total_reservations')->default(0);
            $table->unsignedInteger('approved_reservations')->default(0);
            $table->unsignedInteger('completed_reservations')->default(0);
            $table->unsignedInteger('total_attendees')->default(0);
            $table->json('status_counts');
            $table->json('facility_counts');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->index(['report_type', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_reports');
    }
};
