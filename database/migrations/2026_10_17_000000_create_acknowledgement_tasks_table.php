<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('acknowledgement_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        // Preserve existing prepared receipts; generation never implies staff completion.
        DB::table('reservations')->where('status', 'approved')->orderBy('id')->chunkById(500, function ($rows) {
            $prepared = DB::table('acknowledgement_receipts')->whereIn('reservation_id', $rows->pluck('id'))->pluck('reservation_id')->all();
            DB::table('acknowledgement_tasks')->insertOrIgnore($rows->map(fn ($row) => [
                'reservation_id' => $row->id, 'status' => in_array($row->id, $prepared) ? 'in_progress' : 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acknowledgement_tasks');
    }
};
