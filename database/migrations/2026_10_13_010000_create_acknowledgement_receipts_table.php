<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('acknowledgement_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('receipt_number', 100)->unique();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->json('snapshot');
            $table->string('version', 36);
            $table->text('notes')->nullable();
            $table->timestamp('prepared_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('acknowledgement_receipts');
    }
};
