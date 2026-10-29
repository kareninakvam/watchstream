<?php

namespace Tests\Integration;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\MySqlTestCase;

class AdministrativeAccessTest extends MySqlTestCase
{
    public function test_admin_gates_reject_guests_and_participants_and_allow_administrators(): void
    {
        $participant = User::factory()->create();
        $administrator = User::factory()->create();
        $administrator->forceFill(['is_admin' => true])->save();

        foreach (['access-admin', 'viewHorizon', 'viewPulse'] as $ability) {
            $this->assertFalse(Gate::forUser(null)->allows($ability));
            $this->assertFalse(Gate::forUser($participant)->allows($ability));
            $this->assertTrue(Gate::forUser($administrator)->allows($ability));
        }
    }

    public function test_new_user_is_not_an_administrator(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_admin_permission_cannot_be_granted_by_mass_assignment(): void
    {
        $user = User::factory()->create();
        $user->fill(['is_admin' => true])->save();

        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_guest_cannot_access_administration(): void
    {
        $this->getJson('/admin')->assertUnauthorized();
    }

    public function test_participant_cannot_access_administration(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/admin')->assertForbidden();
    }

    public function test_administrator_can_access_administration(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        $this->actingAs($user)->getJson('/admin')->assertSuccessful();
    }

    public function test_participant_cannot_access_horizon_even_in_local_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->actingAs(User::factory()->create())->get('/horizon')->assertForbidden();
    }

    public function test_participant_cannot_access_pulse_even_in_local_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->actingAs(User::factory()->create())->get('/pulse')->assertForbidden();
    }
}
