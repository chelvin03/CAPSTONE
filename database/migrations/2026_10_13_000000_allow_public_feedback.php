<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('feedback', fn (Blueprint $table) => $table->unsignedBigInteger('user_id')->nullable()->change());
    }

    public function down(): void
    {
        if (DB::table('feedback')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Public feedback exists. Keep it backed up and assign account owners before reversing this migration.');
        }
        Schema::table('feedback', fn (Blueprint $table) => $table->unsignedBigInteger('user_id')->nullable(false)->change());
    }
};
