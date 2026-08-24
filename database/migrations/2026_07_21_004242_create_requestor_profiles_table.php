<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('requestor_profiles', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('student_or_employee_number', 50)->nullable();

            $table->string('organization_name')->nullable();

            $table->string('department_or_office')->nullable();

            $table->string('street_address')->nullable();

            $table->string('barangay', 100)->nullable();

            $table->string('valid_id_type', 100)->nullable();

            $table->string('valid_id_number', 100)->nullable();

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requestor_profiles');
    }
};
