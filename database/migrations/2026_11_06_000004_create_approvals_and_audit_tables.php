<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Decision Room: every Level 2 / Level 3 action waits here for the CEO.
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('code', 8); // short human code used over WhatsApp, e.g. "K7Q2"
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requested_by')->default('ai'); // ai | user | system
            $table->string('action_type'); // see App\Enums\ActionType
            $table->unsignedTinyInteger('authority_level'); // 1 autonomous | 2 CEO approval | 3 CEO only
            $table->string('title');
            $table->text('situation')->nullable();
            $table->text('analysis')->nullable();
            $table->text('recommendation')->nullable();
            $table->text('expected_impact')->nullable();
            $table->json('payload'); // exact parameters that will be executed
            $table->string('payload_hash', 64);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('notified_version')->nullable(); // last version shown to the CEO on WhatsApp
            $table->string('recipient_name')->nullable();
            $table->string('recipient_address')->nullable(); // phone or email
            $table->text('preview')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decided_via')->nullable(); // dashboard | whatsapp
            $table->unsignedInteger('decided_version')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->json('execution_result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'code']);
            $table->index(['business_id', 'status']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor')->default('user'); // user | ai | system | webhook
            $table->string('channel')->nullable();
            $table->string('event');
            $table->nullableMorphs('subject');
            $table->json('data')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('approvals');
    }
};
