<?php

namespace Tests\Feature;

use App\Models\Personen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_completely_delete_a_staff_record_without_login(): void
    {
        $actor = User::factory()->create();
        $this->grantTestPermission($actor, 'benutzer.destroy');
        $staff = Personen::factory()->create([
            'vorname' => 'Test',
            'nachname' => 'Mitarbeiter',
            'typ' => 'mitarbeiter',
        ]);

        $response = $this->actingAs($actor)->deleteJson(
            route('user.staff.destroy', $staff),
            ['confirmation' => 'delete'],
        );

        $response
            ->assertOk()
            ->assertJsonPath('person_id', $staff->id)
            ->assertJsonPath('message', 'Test Mitarbeiter wurde vollständig gelöscht.');
        $this->assertDatabaseMissing('personens', ['id' => $staff->id]);
    }

    public function test_complete_staff_deletion_also_removes_the_linked_login_account(): void
    {
        $actor = User::factory()->create();
        $this->grantTestPermission($actor, 'benutzer.destroy');
        $target = User::factory()->create();
        $personId = $target->person_id;

        $this->actingAs($actor)->deleteJson(
            route('user.staff.destroy', $personId),
            ['confirmation' => 'delete'],
        )->assertOk();

        $this->assertDatabaseMissing('personens', ['id' => $personId]);
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_deleting_an_already_removed_staff_record_is_treated_as_success(): void
    {
        $actor = User::factory()->create();
        $this->grantTestPermission($actor, 'benutzer.destroy');
        $staff = Personen::factory()->create();
        $personId = $staff->id;
        $staff->delete();

        $this->actingAs($actor)->deleteJson(
            route('user.staff.destroy', $personId),
            ['confirmation' => 'delete'],
        )
            ->assertOk()
            ->assertJsonPath('person_id', $personId)
            ->assertJsonPath('already_deleted', true);
    }

    public function test_staff_deletion_status_reports_whether_the_record_still_exists(): void
    {
        $actor = User::factory()->create();
        $this->grantTestPermission($actor, 'benutzer.destroy');
        $staff = Personen::factory()->create();

        $this->actingAs($actor)
            ->getJson(route('user.staff.deletion-status', $staff->id))
            ->assertOk()
            ->assertJsonPath('exists', true);

        $personId = $staff->id;
        $staff->delete();

        $this->actingAs($actor)
            ->getJson(route('user.staff.deletion-status', $personId))
            ->assertOk()
            ->assertJsonPath('exists', false);
    }

    public function test_exact_delete_confirmation_is_required(): void
    {
        $actor = User::factory()->create();
        $this->grantTestPermission($actor, 'benutzer.destroy');
        $staff = Personen::factory()->create();

        $this->actingAs($actor)->deleteJson(
            route('user.staff.destroy', $staff),
            ['confirmation' => 'ja'],
        )->assertUnprocessable()->assertJsonValidationErrors('confirmation');

        $this->assertDatabaseHas('personens', ['id' => $staff->id]);
    }

    public function test_user_cannot_completely_delete_their_own_staff_record(): void
    {
        $actor = User::factory()->create();
        $this->grantTestPermission($actor, 'benutzer.destroy');

        $this->actingAs($actor)->deleteJson(
            route('user.staff.destroy', $actor->person_id),
            ['confirmation' => 'delete'],
        )->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $actor->id]);
        $this->assertDatabaseHas('personens', ['id' => $actor->person_id]);
    }

    public function test_participant_cannot_be_deleted_through_the_staff_endpoint(): void
    {
        $actor = User::factory()->create();
        $this->grantTestPermission($actor, 'benutzer.destroy');
        $participant = Personen::factory()->create(['typ' => 'teilnehmer']);

        $this->actingAs($actor)->deleteJson(
            route('user.staff.destroy', $participant),
            ['confirmation' => 'delete'],
        )->assertUnprocessable();

        $this->assertDatabaseHas('personens', ['id' => $participant->id]);
    }
}
