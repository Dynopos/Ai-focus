<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium'); // low | medium | high | critical
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignee_name')->nullable(); // for people without an account
            $table->dateTime('deadline')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('revenue_potential', 14, 2)->nullable();
            $table->decimal('financial_impact', 14, 2)->nullable();
            $table->unsignedTinyInteger('strategic_importance')->default(3); // 1-5
            $table->string('source')->default('dashboard'); // dashboard | email | whatsapp | ai
            $table->text('completion_evidence')->nullable();
            $table->string('ceo_approval_status')->default('not_required'); // not_required | pending | approved | rejected
            $table->string('dedupe_key')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'deadline']);
            $table->index(['business_id', 'dedupe_key']);
        });

        Schema::create('task_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor')->default('user'); // user | ai | system
            $table->string('channel')->default('dashboard'); // dashboard | email | whatsapp | ai | system
            $table->string('action'); // created | status_changed | updated | reassigned | note
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activities');
        Schema::dropIfExists('tasks');
    }
};
