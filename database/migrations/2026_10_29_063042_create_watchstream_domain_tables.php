<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->boolean('is_admin')->default(false);
        });

        Schema::create('external_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('provider_user_id', 191)->collation('utf8mb4_bin');
            $table->string('display_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_user_id']);
        });

        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('provider_channel_id', 191)->collation('utf8mb4_bin');
            $table->string('name');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('connection_status', 32)->default('disconnected');
            $table->timestamps();
            $table->unique(['provider', 'provider_channel_id']);
        });

        Schema::create('live_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->restrictOnDelete();
            $table->string('provider_stream_id', 191)->collation('utf8mb4_bin');
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('status', 32)->default('active');
            $table->string('scoring_rule_version', 64)->nullable();
            $table->json('scoring_rule')->nullable();
            $table->timestamps();
            $table->unique(['channel_id', 'provider_stream_id']);
            $table->index(['channel_id', 'status', 'started_at']);
        });

        Schema::create('received_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->foreignId('channel_id')->constrained()->restrictOnDelete();
            $table->foreignId('live_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('provider_event_id', 191)->collation('utf8mb4_bin');
            $table->string('provider_user_id', 191)->collation('utf8mb4_bin');
            $table->string('type', 32)->default('chat_message');
            $table->dateTime('occurred_at');
            $table->dateTime('received_at');
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->dateTime('claimed_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestamps();
            $table->unique(['provider', 'channel_id', 'provider_event_id'], 'received_events_source_unique');
            $table->index(['status', 'claimed_at', 'id']);
            $table->index(['live_session_id', 'provider_user_id', 'occurred_at'], 'received_events_participation_index');
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('balance')->default(0);
            $table->timestamps();
        });

        Schema::create('rewards', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->text('conditions')->nullable();
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'id']);
        });

        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('reward_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 191)->collation('utf8mb4_bin');
            $table->unsignedBigInteger('price');
            $table->string('reward_title');
            $table->text('reward_description');
            $table->text('reward_conditions')->nullable();
            $table->string('status', 32)->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at', 'id']);
            $table->index(['status', 'created_at', 'id']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignId('live_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('redemption_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('window_index')->nullable();
            $table->bigInteger('amount');
            $table->string('type', 16);
            $table->string('origin_key', 191)->collation('utf8mb4_bin')->unique();
            $table->timestamps();
            $table->index(['wallet_id', 'created_at', 'id']);
            $table->unique(['wallet_id', 'live_session_id', 'window_index'], 'ledger_entries_live_window_unique');
            $table->unique(['redemption_id', 'type'], 'ledger_entries_redemption_type_unique');
        });

        // MySQL 8.4 e o banco suportado do projeto; as restricoes nao dependem do cliente.
        DB::statement('ALTER TABLE rewards ADD CONSTRAINT rewards_positive_price CHECK (price > 0)');
        DB::statement('ALTER TABLE redemptions ADD CONSTRAINT redemptions_positive_price CHECK (price > 0)');
        DB::statement("ALTER TABLE redemptions ADD CONSTRAINT redemptions_valid_status CHECK (status IN ('pending', 'delivered', 'cancelled'))");
        DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_signed_amount CHECK ((type IN ('credit', 'refund') AND amount > 0) OR (type = 'debit' AND amount < 0))");
        DB::statement('ALTER TABLE live_sessions ADD CONSTRAINT live_sessions_valid_period CHECK (ended_at IS NULL OR ended_at >= started_at)');
    }

    public function down(): void
    {
        foreach (['ledger_entries', 'redemptions', 'rewards', 'wallets', 'received_events', 'live_sessions', 'channels', 'external_identities'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
        // Email/senha continuam nullable para nao invalidar contas OAuth existentes.
    }
};
