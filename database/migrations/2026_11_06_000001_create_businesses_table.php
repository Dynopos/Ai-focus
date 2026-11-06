<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('timezone')->default('Asia/Kuala_Lumpur');
            $table->text('profile')->nullable();
            $table->text('goals')->nullable();
            // WhatsApp Cloud API phone number id that receives this tenant's messages.
            $table->string('whatsapp_phone_number_id')->nullable()->unique();
            $table->string('report_email')->nullable();
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('ceo')->after('email'); // ceo | manager | staff
            $table->string('phone')->nullable()->after('role'); // E.164 without "+", e.g. 60123456789
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->timestamp('whatsapp_opt_in_at')->nullable()->after('phone_verified_at');
            $table->timestamp('whatsapp_last_inbound_at')->nullable()->after('whatsapp_opt_in_at');
            // CEO proves phone ownership by sending "VERIFY <code>" from that phone.
            $table->string('phone_verification_hash')->nullable()->after('whatsapp_last_inbound_at');
            $table->timestamp('phone_verification_expires_at')->nullable()->after('phone_verification_hash');
            $table->index(['business_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
            $table->dropColumn(['role', 'phone', 'phone_verified_at', 'whatsapp_opt_in_at', 'whatsapp_last_inbound_at', 'phone_verification_hash', 'phone_verification_expires_at']);
        });
        Schema::dropIfExists('businesses');
    }
};
