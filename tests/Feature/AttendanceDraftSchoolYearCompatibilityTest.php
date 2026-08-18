<?php

namespace Tests\Feature;

use App\Models\BibbAttendanceListDraft;
use App\Models\PaAttendanceListDraft;
use App\Models\Partner;
use App\Models\Projekt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceDraftSchoolYearCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pa_restores_signatures_saved_with_the_old_school_year_format(): void
    {
        [$user, $project, $school] = $this->attendanceContext();
        $signature = 'data:image/png;base64,aGVsbG8=';
        $legacyHash = hash('sha256', implode('|', [
            $project->id,
            $school->id,
            '2026/2027',
            '1',
            'alle',
            '',
            'pa',
            'pa-attendance-list',
        ]));

        PaAttendanceListDraft::query()->create([
            'draft_hash' => $legacyHash,
            'projekt_id' => $project->id,
            'partner_id' => $school->id,
            'schuljahr' => '2026/2027',
            'teil' => '1',
            'export_mode' => 'alle',
            'payload' => [
                'version' => 1,
                'form' => ['startDate' => '2026-08-18'],
                'days' => [],
                'selectedDayId' => null,
                'signatures' => ['day-1:person-1' => $signature],
            ],
            'revision' => 1,
        ]);

        $scope = [
            'schuleId' => $school->id,
            'schuljahr' => '2026',
            'teil' => '1',
            'listType' => 'pa',
            'exportMode' => 'alle',
        ];

        $this->actingAs($user)
            ->postJson(route('anwesenheitsliste.PA.digital.draft.show'), $scope)
            ->assertOk()
            ->assertJsonPath('exists', true)
            ->assertJsonPath('restored_from_schuljahr', '2026/2027')
            ->assertJsonPath('payload.signatures.day-1:person-1', $signature);

        $this->actingAs($user->fresh())
            ->putJson(route('anwesenheitsliste.PA.digital.draft.store'), $scope + [
                'payload' => [
                    'version' => 1,
                    'form' => ['startDate' => '2026-08-18'],
                    'days' => [],
                    'selectedDayId' => null,
                    'signatures' => [],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('payload.signatures.day-1:person-1', $signature);

        $this->assertDatabaseMissing('pa_attendance_list_drafts', ['draft_hash' => $legacyHash]);
        $currentDraft = PaAttendanceListDraft::query()->where('schuljahr', '2026')->sole();
        $this->assertStringStartsWith('enc:v1:', $currentDraft->payload['signatures']['day-1:person-1']);
    }

    public function test_bibb_restores_signatures_saved_with_the_old_school_year_format(): void
    {
        [$user, $project, $school] = $this->attendanceContext();
        $signature = 'data:image/png;base64,aGVsbG8=';
        $legacyHash = hash('sha256', implode('|', [
            $project->id,
            $school->id,
            '2026/2027',
            '1',
            'bibb-attendance-list',
        ]));

        BibbAttendanceListDraft::query()->create([
            'draft_hash' => $legacyHash,
            'projekt_id' => $project->id,
            'partner_id' => $school->id,
            'schuljahr' => '2026/2027',
            'teil' => '1',
            'payload' => [
                'form' => [],
                'days' => [],
                'selectedDayId' => null,
                'signatures' => ['day-1:person-1' => $signature],
            ],
            'revision' => 1,
        ]);

        $scope = [
            'schuleIdInputBibb' => $school->id,
            'schuljahrInputBibb' => '2026',
            'teilInputBibb' => '1',
        ];

        $this->actingAs($user)
            ->postJson(route('anwesenheitsliste.POBO.bibb.draft.show'), $scope)
            ->assertOk()
            ->assertJsonPath('exists', true)
            ->assertJsonPath('restored_from_schuljahr', '2026/2027')
            ->assertJsonPath('payload.signatures.day-1:person-1', $signature);

        $this->actingAs($user->fresh())
            ->putJson(route('anwesenheitsliste.POBO.bibb.draft.store'), $scope + [
                'payload' => [
                    'form' => [],
                    'days' => [],
                    'selectedDayId' => null,
                    'signatures' => [],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('payload.signatures.day-1:person-1', $signature);

        $this->assertDatabaseMissing('bibb_attendance_list_drafts', ['draft_hash' => $legacyHash]);
        $currentDraft = BibbAttendanceListDraft::query()->where('schuljahr', '2026')->sole();
        $this->assertStringStartsWith('enc:v1:', $currentDraft->payload['signatures']['day-1:person-1']);
    }

    private function attendanceContext(): array
    {
        $user = User::factory()->create();
        $this->grantTestPermission($user, 'anwesenheit.abrechnung');
        $project = Projekt::factory()->create();
        $school = Partner::query()->create(['name' => 'Testschule']);

        DB::table('projekt_has_partners')->insert([
            'projekt_id' => $project->id,
            'partner_id' => $school->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('projekt_has_personens')->insert([
            'projekt_id' => $project->id,
            'personen_id' => $user->person_id,
            'status' => 'aktiv',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user->update(['current_team_id' => $project->id]);

        return [$user, $project, $school];
    }
}
