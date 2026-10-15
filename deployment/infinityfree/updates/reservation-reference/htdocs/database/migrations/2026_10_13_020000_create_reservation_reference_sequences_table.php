<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_reference_sequences', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });

        DB::table('reservation_reference_sequences')->insert([
            'id' => 1,
            'last_number' => DB::table('reservations')->count(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_reference_sequences');
    }
};
