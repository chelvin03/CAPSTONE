<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->string('requested_equipment')->nullable()->after('cleanup_time');
            $table->unsignedInteger('requested_equipment_quantity')->nullable()->after('requested_equipment');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropColumn(['requested_equipment', 'requested_equipment_quantity']);
        });
    }
};
