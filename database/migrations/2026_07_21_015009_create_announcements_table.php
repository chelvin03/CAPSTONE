<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('title', 200);
            $table->text('content');

            $table->string('audience', 50)
                ->default('all')
                ->index();

            $table->string('status', 30)
                ->default('draft')
                ->index();

            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(
                ['status', 'published_at', 'expires_at'],
                'announcements_visibility_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
