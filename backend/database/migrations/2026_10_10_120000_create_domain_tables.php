<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faz 1 veri modeli (spec §5). Enum kolonları string; PHP backed enum'larla cast edilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_requests', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['phone', 'created_at']);
        });

        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 10);
            $table->string('fcm_token', 512)->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('salons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('logo_path')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('salon_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable()->unique();
            // İlk girişte e-postadaki bağlantıyla belirlenir (spec §2).
            $table->string('password')->nullable();
            $table->string('role', 10);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('salon_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('created_by', 10);
            $table->string('type', 20);
            $table->string('title');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('venue_name')->nullable();
            $table->string('venue_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('couple_phone', 20)->nullable()->index();
            $table->boolean('share_headcount_with_salon')->default(true);
            $table->timestamp('pack_purchased_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['salon_id', 'starts_at']);
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salon_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->unique()->constrained()->restrictOnDelete();
            $table->string('contact_name');
            $table->string('contact_phone', 20);
            $table->string('status', 10);
            // Kuruş cinsinden.
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->unsignedBigInteger('deposit_amount')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->jsonb('event_types');
            $table->boolean('is_premium')->default(false);
            $table->jsonb('config');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained()->restrictOnDelete();
            $table->jsonb('texts');
            $table->string('envelope_color', 20);
            $table->string('seal_color', 20);
            $table->string('seal_text', 4)->nullable();
            $table->date('rsvp_deadline')->nullable();
            $table->timestamp('first_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invitation_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20)->index();
            $table->char('phone_hash', 64);
            $table->string('name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('token', 40)->unique();
            $table->string('channel', 10)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->string('rsvp_status', 20)->default('pending');
            $table->unsignedTinyInteger('guest_count')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['invitation_id', 'phone']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('platform', 10);
            $table->string('product_id');
            $table->string('transaction_id')->unique();
            $table->string('status', 10);
            $table->jsonb('payload')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('recipients');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('templates');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('events');
        Schema::dropIfExists('salon_users');
        Schema::dropIfExists('salons');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('otp_requests');
    }
};
