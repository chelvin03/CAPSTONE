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
            $table->dropForeign(['user_id']);
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')
                ->nullable()
                ->change();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->string('contact_email')
                ->nullable()
                ->after('contact_person');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropColumn('contact_email');
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')
                ->nullable(false)
                ->change();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }
};
