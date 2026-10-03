<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'can_priority_override')) Schema::table('users', fn (Blueprint $table) => $table->boolean('can_priority_override')->default(false)->after('role'));
        if (! Schema::hasTable('schedule_blocks')) Schema::create('schedule_blocks', function (Blueprint $table): void {
            $table->id(); $table->foreignId('facility_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('starts_on'); $table->date('ends_on'); $table->time('start_time')->nullable(); $table->time('end_time')->nullable();
            $table->string('title'); $table->text('reason')->nullable(); $table->foreignId('created_by')->constrained('users'); $table->timestamps();
            $table->index(['starts_on', 'ends_on']);
        });
        if (! Schema::hasTable('system_settings')) Schema::create('system_settings', function (Blueprint $table): void { $table->id(); $table->string('key')->unique(); $table->text('value')->nullable(); $table->timestamps(); });
        if (! Schema::hasTable('email_templates')) Schema::create('email_templates', function (Blueprint $table): void { $table->id(); $table->string('key')->unique(); $table->string('subject'); $table->text('body'); $table->timestamps(); });
    }
    public function down(): void
    {
        Schema::dropIfExists('email_templates'); Schema::dropIfExists('system_settings'); Schema::dropIfExists('schedule_blocks');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('can_priority_override'));
    }
};
