<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('organization_department')->nullable();
            $table->text('additional_notes')->nullable();
        });
    }
    public function down(): void {
        Schema::table('reservations', fn (Blueprint $table) => $table->dropColumn(['organization_department', 'additional_notes']));
    }
};
