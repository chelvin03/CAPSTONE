<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('reservation_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');

            $table->text('comments')->nullable();

            $table->boolean('is_anonymous')
                ->default(false);

            $table->timestamps();

            $table->unique(
                ['reservation_id', 'user_id'],
                'feedback_unique_reservation_user'
            );

            $table->index(
                ['rating', 'created_at'],
                'feedback_rating_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
