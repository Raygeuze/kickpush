<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTeamNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_names_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        $this->put('/teams/'.$user->currentTeam->id, [
            'name' => 'Test Team',
        ]);

        $this->assertCount(1, $user->fresh()->ownedTeams);
        $this->assertEquals('Test Team', $user->currentTeam->fresh()->name);
    }

    public function test_team_owner_can_update_the_reporting_timezone(): void
    {
        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        $this->put('/teams/'.$user->currentTeam->id, [
            'name' => $user->currentTeam->name,
            'timezone' => 'Pacific/Auckland',
        ]);

        $this->assertSame('Pacific/Auckland', $user->currentTeam->fresh()->timezone);
    }

    public function test_team_admin_can_update_the_reporting_timezone(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $admin = User::factory()->create([
            'current_team_id' => $owner->currentTeam->id,
        ]);
        $owner->currentTeam->users()->attach($admin, ['role' => 'admin']);

        $this->actingAs($admin)->put('/teams/'.$owner->currentTeam->id, [
            'name' => $owner->currentTeam->name,
            'timezone' => 'Australia/Sydney',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Australia/Sydney', $owner->currentTeam->fresh()->timezone);
    }

    public function test_employee_cannot_update_the_reporting_timezone(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $employee = User::factory()->create([
            'current_team_id' => $owner->currentTeam->id,
        ]);
        $owner->currentTeam->users()->attach($employee, ['role' => 'employee']);

        $this->actingAs($employee)->put('/teams/'.$owner->currentTeam->id, [
            'name' => $owner->currentTeam->name,
            'timezone' => 'Pacific/Auckland',
        ])->assertForbidden();

        $this->assertSame('UTC', $owner->currentTeam->fresh()->timezone);
    }

    public function test_timezone_must_be_a_valid_iana_identifier(): void
    {
        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        $this->put('/teams/'.$user->currentTeam->id, [
            'name' => $user->currentTeam->name,
            'timezone' => 'Middle/Earth',
        ])->assertSessionHasErrors('timezone');

        $this->assertSame('UTC', $user->currentTeam->fresh()->timezone);
    }
}
