<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_equipment', function (Blueprint $table) {
            $table->string('status')->default('pending');
            $table->unsignedInteger('quantity_offered')->nullable();
            $table->timestamp('reply_sent_at')->nullable();
            $table->boolean('requires_response')->default(false);
            $table->uuid('latest_offer_id')->nullable();
            $table->string('requestor_response')->nullable();
            $table->timestamp('requestor_responded_at')->nullable();
        });
        DB::table('reservation_equipment')->where('quantity_approved', '>', 0)->update(['status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('reservation_equipment', fn (Blueprint $table) => $table->dropColumn([
            'status', 'quantity_offered', 'reply_sent_at', 'requires_response', 'latest_offer_id',
            'requestor_response', 'requestor_responded_at',
        ]));
    }
};
