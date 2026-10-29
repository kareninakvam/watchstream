<?php

namespace Tests\Integration;

use App\Models\Channel;
use App\Models\ExternalIdentity;
use App\Models\LedgerEntry;
use App\Models\LiveSession;
use App\Models\ReceivedEvent;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use App\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\MySqlTestCase;

class DomainFoundationTest extends MySqlTestCase
{
    public function test_migrations_create_the_domain_tables(): void
    {
        foreach (['users', 'external_identities', 'channels', 'live_sessions', 'received_events', 'wallets', 'ledger_entries', 'rewards', 'redemptions'] as $table) {
            $this->assertTrue(Schema::hasTable($table), 'Tabela ausente: '.$table);
        }
    }

    public function test_provider_identity_cannot_belong_to_two_users(): void
    {
        $this->identity(User::factory()->create());

        $this->expectException(QueryException::class);
        $this->identity(User::factory()->create());
    }

    public function test_same_external_id_can_exist_in_different_providers(): void
    {
        $user = User::factory()->create();
        $this->identity($user);
        $other = $this->identity($user, ['provider' => 'youtube']);

        $this->assertSame('youtube', $other->fresh()->provider);
    }

    public function test_changing_display_name_keeps_the_same_identity(): void
    {
        $identity = $this->identity(User::factory()->create());
        $identity->update(['display_name' => 'Novo nome']);

        $this->assertSame('viewer-123', $identity->fresh()->provider_user_id);
        $this->assertDatabaseCount('external_identities', 1);
    }

    public function test_tokens_are_encrypted_at_rest_and_hidden_from_serialization(): void
    {
        $identity = $this->identity(User::factory()->create(), [
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
        ]);
        $stored = DB::table('external_identities')->where('id', $identity->id)->first();

        $this->assertNotSame('test-access-token', $stored->access_token);
        $this->assertNotSame('test-refresh-token', $stored->refresh_token);
        $this->assertSame('test-access-token', $identity->fresh()->access_token);
        $this->assertSame('test-refresh-token', $identity->fresh()->refresh_token);
        $this->assertArrayNotHasKey('access_token', $identity->fresh()->toArray());
        $this->assertArrayNotHasKey('refresh_token', $identity->fresh()->toArray());
    }

    public function test_oauth_user_does_not_require_email_or_local_password(): void
    {
        $user = User::create(['name' => 'Participante']);

        $this->assertNull($user->fresh()->email);
        $this->assertNull($user->fresh()->password);
    }

    public function test_user_has_only_one_wallet(): void
    {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0]);

        $this->expectException(QueryException::class);
        Wallet::create(['user_id' => $user->id, 'balance' => 0]);
    }

    public function test_wallet_rejects_negative_balance_in_database(): void
    {
        $this->assertTrue(Schema::hasTable('wallets'));
        $this->expectException(QueryException::class);
        DB::table('wallets')->insert(['user_id' => User::factory()->create()->id, 'balance' => -1]);
    }

    public function test_foreign_key_rejects_wallet_without_user(): void
    {
        $this->assertTrue(Schema::hasTable('wallets'));
        $this->expectException(QueryException::class);
        DB::table('wallets')->insert(['user_id' => 999999, 'balance' => 0]);
    }

    public function test_wallet_returns_an_integer_balance(): void
    {
        $wallet = Wallet::create(['user_id' => User::factory()->create()->id, 'balance' => 10]);

        $this->assertSame(10, $wallet->fresh()->balance);
    }

    public function test_live_is_unique_per_channel_and_provider_stream_id(): void
    {
        $channel = $this->channel();
        $this->live($channel);

        $this->expectException(QueryException::class);
        $this->live($channel);
    }

    public function test_received_event_is_unique_per_provider_channel_and_event_id(): void
    {
        $channel = $this->channel();
        $event = [
            'provider' => 'twitch', 'channel_id' => $channel->id,
            'provider_event_id' => 'message-1', 'provider_user_id' => 'viewer-123',
            'occurred_at' => CarbonImmutable::now('UTC'), 'received_at' => CarbonImmutable::now('UTC'),
        ];
        ReceivedEvent::create($event);

        $this->expectException(QueryException::class);
        ReceivedEvent::create($event);
    }

    public function test_business_timestamp_is_normalized_to_utc(): void
    {
        $live = $this->live($this->channel(), [
            'started_at' => CarbonImmutable::parse('2026-10-29 12:00:00', 'America/Sao_Paulo'),
        ]);

        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('2026-10-29 15:00:00', DB::table('live_sessions')->where('id', $live->id)->value('started_at'));
        $this->assertSame('2026-10-29 15:00:00', $live->fresh()->started_at->format('Y-m-d H:i:s'));
    }

    public function test_reward_price_and_stock_are_validated_by_database(): void
    {
        $this->expectException(QueryException::class);
        Reward::create(['title' => 'Item', 'description' => 'Descricao', 'price' => 0, 'stock' => 1]);
    }

    public function test_reward_stock_cannot_be_negative(): void
    {
        $this->assertTrue(Schema::hasTable('rewards'));
        $this->expectException(QueryException::class);
        DB::table('rewards')->insert(['title' => 'Item', 'description' => 'Descricao', 'price' => 10, 'stock' => -1]);
    }

    public function test_redemption_preserves_snapshot_and_has_a_unique_user_key(): void
    {
        $user = User::factory()->create();
        $reward = Reward::create(['title' => 'Item original', 'description' => 'Condicoes', 'price' => 10, 'stock' => 2]);
        $data = [
            'user_id' => $user->id, 'reward_id' => $reward->id,
            'idempotency_key' => 'request-1', 'price' => 10,
            'reward_title' => 'Item original', 'reward_description' => 'Condicoes',
        ];
        $redemption = Redemption::create($data);
        $reward->update(['title' => 'Novo item', 'price' => 20]);
        $this->assertSame(10, $redemption->fresh()->price);
        $this->assertSame('Item original', $redemption->fresh()->reward_title);
        $this->assertSame('pending', $redemption->fresh()->status);

        $this->expectException(QueryException::class);
        Redemption::create($data);
    }

    public function test_ledger_origin_cannot_be_credited_twice(): void
    {
        $wallet = Wallet::create(['user_id' => User::factory()->create()->id, 'balance' => 0]);
        $data = ['wallet_id' => $wallet->id, 'amount' => 10, 'type' => 'credit', 'origin_key' => 'live:1:window:0:user:1'];
        LedgerEntry::create($data);

        $this->expectException(QueryException::class);
        LedgerEntry::create($data);
    }

    public function test_user_with_history_cannot_be_deleted_accidentally(): void
    {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 10]);

        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $user->id)->delete();
    }

    public function test_channel_authorization_tokens_are_encrypted_and_hidden(): void
    {
        $channel = Channel::factory()->create(['access_token' => 'test-channel-token', 'refresh_token' => 'test-channel-refresh']);
        $stored = DB::table('channels')->where('id', $channel->id)->first();

        $this->assertNotSame('test-channel-token', $stored->access_token);
        $this->assertNotSame('test-channel-refresh', $stored->refresh_token);
        $this->assertSame('test-channel-token', $channel->fresh()->access_token);
        $this->assertSame('test-channel-refresh', $channel->fresh()->refresh_token);
        $this->assertArrayNotHasKey('access_token', $channel->fresh()->toArray());
        $this->assertArrayNotHasKey('refresh_token', $channel->fresh()->toArray());
    }

    public function test_external_provider_ids_are_case_sensitive(): void
    {
        $user = User::factory()->create();
        $this->identity($user, ['provider_user_id' => 'AbC']);
        $this->identity($user, ['provider_user_id' => 'abc']);

        $this->assertDatabaseCount('external_identities', 2);
    }

    public function test_live_cannot_end_before_it_starts(): void
    {
        $channel = $this->channel();

        $this->expectException(QueryException::class);
        $this->live($channel, ['started_at' => '2026-10-29 15:00:00', 'ended_at' => '2026-10-29 14:59:59']);
    }

    public function test_ledger_rejects_credit_with_negative_amount(): void
    {
        $wallet = Wallet::factory()->create();

        $this->expectException(QueryException::class);
        LedgerEntry::factory()->create(['wallet_id' => $wallet->id, 'amount' => -10, 'type' => 'credit']);
    }

    public function test_redemption_cannot_be_deleted_while_linked_to_ledger(): void
    {
        $redemption = Redemption::factory()->create();
        $wallet = Wallet::factory()->for($redemption->user)->create();
        $entry = LedgerEntry::factory()->for($wallet)->for($redemption)->create(['type' => 'debit', 'amount' => -10]);
        $this->assertSame($redemption->user_id, $entry->wallet->user_id);
        $this->assertSame($redemption->reward->title, $redemption->reward_title);

        $this->expectException(QueryException::class);
        $redemption->delete();
    }

    private function identity(User $user, array $attributes = []): ExternalIdentity
    {
        return ExternalIdentity::create(array_merge([
            'user_id' => $user->id, 'provider' => 'twitch',
            'provider_user_id' => 'viewer-123', 'display_name' => 'Participante',
        ], $attributes));
    }

    private function channel(): Channel
    {
        return Channel::create(['provider' => 'twitch', 'provider_channel_id' => 'channel-1', 'name' => 'Canal']);
    }

    private function live(Channel $channel, array $attributes = []): LiveSession
    {
        return LiveSession::create(array_merge([
            'channel_id' => $channel->id, 'provider_stream_id' => 'stream-1',
            'started_at' => CarbonImmutable::now('UTC'),
        ], $attributes));
    }
}
