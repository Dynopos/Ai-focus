<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('channel'); // email | whatsapp
            $table->string('direction'); // outbound | inbound
            $table->string('type'); // morning_briefing | evening_report | wa_priorities | wa_progress | approval_request | ceo_reply | customer_message | alert | inbound_ceo | inbound_customer
            $table->string('recipient')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->json('payload')->nullable(); // template name / params etc.
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable()->index();
            $table->string('status')->default('queued'); // queued | sending | sent | delivered | read | failed | skipped | received
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->json('provider_response')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->foreignId('approval_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'channel', 'created_at']);
            $table->index(['business_id', 'status']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('event_id');
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload');
            $table->string('status')->default('received'); // received | processed | ignored | failed
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('key'); // morning_email | wa_priorities | wa_progress | evening_email
            $table->boolean('enabled')->default(false);
            $table->string('send_time', 5); // HH:MM in business timezone
            $table->string('channel'); // email | whatsapp
            $table->boolean('respect_quiet_hours')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'key']);
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('automation_key');
            $table->date('run_date'); // local date in business timezone
            $table->string('status')->default('dispatched'); // dispatched | completed | skipped | failed
            $table->text('note')->nullable();
            $table->timestamps();
            // One run per automation per local day: prevents duplicate briefings.
            $table->unique(['business_id', 'automation_key', 'run_date']);
        });

        Schema::create('memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('category'); // company_profile | goal | project | customer | decision | commitment | priority | outcome
            $table->string('title');
            $table->text('content');
            $table->boolean('is_verified')->default(false); // verified fact vs assumption
            $table->string('source')->default('ceo'); // ceo | ai | system
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memories');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('automations');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('communication_logs');
    }
};
