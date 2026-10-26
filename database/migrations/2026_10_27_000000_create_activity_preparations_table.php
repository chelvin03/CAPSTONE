<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_preparations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('task', 40);
            $table->boolean('completed')->default(false);
            $table->string('snapshot_hash', 64);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['reservation_id', 'task']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_preparations');
    }
};
