<?php

namespace App\Services\Legacy;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use Yasumi\Yasumi;

class BopImportService
{
    public const SOURCE = 'bop';

    public const MAPPING_VERSION = '2026-07-13.1';

    public const LOCATION_NAME = 'Ernst-Abbe-9';

    public const PARTNERSHIP_TYPE = 'Kooperationsschule';

    public const PROJECT_NAME = 'Bop';

    public function inspect(): array
    {
        $source = $this->source();
        $this->assertSafeSource($source);

        foreach (['schules', 'teilnehmers', 'bereiches', 'gruppes', 'gruppe_has_teilnehmer', 'anwesenheitslistes'] as $table) {
            if (! $source->getSchemaBuilder()->hasTable($table)) {
                throw new RuntimeException("BOP-Quelltabelle {$table} fehlt.");
            }
        }

        $partnershipTypeExists = DB::table('partnerschaftstypens')
            ->where('bezeichnung', self::PARTNERSHIP_TYPE)
            ->exists();
        $project = DB::table('projekts')->whereRaw('LOWER(name) = ?', [Str::lower(self::PROJECT_NAME)])->first();

        $attendancePreview = $this->attendancePreview();

        return [
            'source_database' => $source->getDatabaseName(),
            'schools' => $source->table('schules')->count(),
            'participants' => $source->table('teilnehmers')->count(),
            'areas' => $source->table('bereiches')->count(),
            'groups' => $source->table('gruppes')->count(),
            'group_memberships' => $source->table('gruppe_has_teilnehmer')->count(),
            'attendance_rows_reconstructable' => $attendancePreview['rows'],
            'attendance_conflicts' => $attendancePreview['conflicts'],
            'participant_duplicates' => $source->table('teilnehmers')
                ->select(['vorname', 'nachname', 'geburtsdatum'])
                ->groupBy(['vorname', 'nachname', 'geburtsdatum'])
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count(),
            'location_exists' => DB::table('standorts')->where('name', self::LOCATION_NAME)->exists(),
            'partnership_type_exists' => $partnershipTypeExists,
            'project_exists' => $project !== null,
            'project_id' => $project?->id,
        ];
    }

    private function attendancePreview(): array
    {
        $rows = 0;
        $conflicts = 0;
        $membershipCounts = $this->source()->table('gruppe_has_teilnehmer')->selectRaw('gruppe_id, COUNT(*) count')->groupBy('gruppe_id')->pluck('count', 'gruppe_id');

        foreach ($this->source()->table('gruppes')->select(['id', 'anfangsdatum', 'enddatum'])->cursor() as $group) {
            $memberships = (int) ($membershipCounts[$group->id] ?? 0);
            $workdayCount = count($this->workdays($group->anfangsdatum, $group->enddatum, 3));
            $rows += $memberships * $workdayCount;
            $conflicts += $memberships * (3 - $workdayCount);
        }

        return compact('rows', 'conflicts');
    }

    public function import(): array
    {
        $inspection = $this->inspect();

        if (! $inspection['partnership_type_exists']) {
            throw new RuntimeException('Partnerschaftstyp "'.self::PARTNERSHIP_TYPE.'" fehlt in ZBB.');
        }
        if (! $inspection['project_exists']) {
            throw new RuntimeException('Zielprojekt "'.self::PROJECT_NAME.'" fehlt in ZBB.');
        }

        $runId = DB::table('legacy_import_runs')->insertGetId([
            'source' => self::SOURCE,
            'mapping_version' => self::MAPPING_VERSION,
            'status' => 'running',
            'dry_run' => false,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summary = array_merge($inspection, [
            'run_id' => $runId,
            'schools_imported' => 0,
            'school_contacts_imported' => 0,
            'participants_imported' => 0,
            'areas_imported' => 0,
            'groups_imported' => 0,
            'attendance_rows_imported' => 0,
            'attendance_conflicts' => 0,
            'selections_imported' => 0,
            'assignments_imported' => 0,
            'pa_ratings_imported' => 0,
            'pa_exercise_results_imported' => 0,
            'bo_ratings_imported' => 0,
            'failed' => 0,
        ]);

        try {
            DB::transaction(function () use ($runId, &$summary): void {
                $locationId = DB::table('standorts')->where('name', self::LOCATION_NAME)->value('id');
                if (! $locationId) {
                    $locationId = DB::table('standorts')->insertGetId([
                        'name' => self::LOCATION_NAME,
                        'beschreibung' => 'Standort der aus dem BOP-Altsystem migrierten Teilnehmer',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $partnershipTypeId = DB::table('partnerschaftstypens')
                    ->where('bezeichnung', self::PARTNERSHIP_TYPE)
                    ->value('id');
                $projectId = DB::table('projekts')
                    ->whereRaw('LOWER(name) = ?', [Str::lower(self::PROJECT_NAME)])
                    ->value('id');

                $schoolMap = $this->importSchools($runId, (int) $partnershipTypeId, $summary);
                $participantMaps = $this->importParticipants($runId, (int) $projectId, (int) $locationId, $schoolMap, $summary);
                $areaMap = $this->importAreas($runId, (int) $projectId, $summary);
                $staffMap = $this->importLegacyStaff($runId);
                $groupMap = $this->importGroups($runId, (int) $projectId, (int) $locationId, $areaMap, $staffMap, $schoolMap, $summary);
                $this->importGroupAttendance($runId, $participantMaps['persons'], $groupMap, $staffMap, $summary);
                $this->importSelectionsAndAssignments($runId, $participantMaps['students'], $areaMap, $summary);
                $this->importPotentialAnalysis($runId, (int) $projectId, $participantMaps['persons'], $groupMap, $summary);
                $this->importBoRatings($runId, $participantMaps['persons'], $groupMap, $summary);
            });

            $readCount = $summary['schools'] + $summary['participants'] + $summary['areas'] + $summary['groups'] + $summary['group_memberships'];
            DB::table('legacy_import_runs')->where('id', $runId)->update([
                'status' => 'completed',
                'read_count' => $readCount,
                'imported_count' => $summary['schools_imported'] + $summary['school_contacts_imported'] + $summary['participants_imported'] + $summary['areas_imported'] + $summary['groups_imported'] + $summary['attendance_rows_imported'] + $summary['selections_imported'] + $summary['assignments_imported'] + $summary['pa_ratings_imported'] + $summary['pa_exercise_results_imported'] + $summary['bo_ratings_imported'],
                'failed_count' => $summary['failed'],
                'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            DB::table('legacy_import_runs')->where('id', $runId)->update([
                'status' => 'failed',
                'failed_count' => 1,
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
                'updated_at' => now(),
            ]);

            throw $exception;
        }

        return $summary;
    }

    private function importSchools(int $runId, int $partnershipTypeId, array &$summary): array
    {
        $map = [];

        foreach ($this->source()->table('schules')->orderBy('id')->cursor() as $school) {
            $payload = (array) $school;
            $checksum = $this->checksum($payload);
            $partnerId = $this->mappedTargetId('schules', (string) $school->id, 'partners');

            if ($partnerId) {
                DB::table('partners')->where('id', $partnerId)->update([
                    'name' => $school->schule,
                    'updated_at' => now(),
                ]);
            } else {
                $partnerId = DB::table('partners')->whereRaw('LOWER(name) = ?', [Str::lower($school->schule)])->value('id');
                if ($partnerId) {
                    DB::table('partners')->where('id', $partnerId)->update([
                        'name' => $school->schule,
                        'beschreibung' => 'Aus BOP migrierte Kooperationsschule',
                        'updated_at' => $school->updated_at ?? now(),
                    ]);
                } else {
                    $partnerId = DB::table('partners')->insertGetId([
                        'name' => $school->schule,
                        'beschreibung' => 'Aus BOP migrierte Kooperationsschule',
                        'created_at' => $school->created_at ?? now(),
                        'updated_at' => $school->updated_at ?? now(),
                    ]);
                }
            }

            DB::table('partner_has_partnerschaftstypens')->updateOrInsert(
                ['partner_id' => $partnerId, 'partnerschaftstypen_id' => $partnershipTypeId],
                ['updated_at' => now(), 'created_at' => now()]
            );

            DB::table('adresses')->updateOrInsert(
                ['model_type' => 'App\\Models\\Partner', 'model_id' => $partnerId],
                [
                    'strasse' => $school->{'straße'},
                    'hausnummer' => $school->nummer,
                    'plz' => $school->postleizahl,
                    'stadt' => $school->ort,
                    'land' => 'Deutschland',
                    'zusatzinfo' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $this->importSchoolContactPerson(
                $runId,
                $school,
                (int) $partnerId,
                $partnershipTypeId,
                $projectId
            );
            $summary['school_contacts_imported']++;

            $this->storeMapping($runId, 'schules', (string) $school->id, 'partners', $partnerId, $checksum);
            $this->storeSnapshot($runId, 'schules', (string) $school->id, $payload, 'partially_imported', 'BOP-spezifische Zeit- und Statusfelder bleiben im Snapshot erhalten.');
            $map[(int) $school->id] = $partnerId;
            $summary['schools_imported']++;
        }

        return $map;
    }

    private function importSchoolContactPerson(int $runId, object $school, int $partnerId, int $partnershipTypeId, int $projectId): void
    {
        $payload = [
            'schule_id' => $school->id,
            'anrede' => $school->anrede,
            'ansprechpartner' => $school->ansprechpartner,
            'tel' => $school->tel,
            'handy' => $school->handy,
            'email' => $school->email,
        ];
        $personId = $this->mappedTargetId('schules_ansprechpartner', (string) $school->id, 'personens');
        [$firstName, $lastName] = $this->splitName((string) $school->ansprechpartner);
        $gender = match (Str::lower(trim((string) $school->anrede))) {
            'frau' => 'w',
            'herr' => 'm',
            default => 'd',
        };
        $values = [
            'vorname' => $firstName,
            'nachname' => $lastName,
            'geschlecht' => $gender,
            'geburtsdatum' => null,
            'aktiv' => true,
            'typ' => 'ansprechpartner',
            'updated_at' => $school->updated_at ?? now(),
        ];

        if ($personId) {
            DB::table('personens')->where('id', $personId)->update($values);
        } else {
            $personId = DB::table('personens')->insertGetId($values + [
                'created_at' => $school->created_at ?? now(),
            ]);
        }

        $contactTypes = DB::table('kontakttypens')->pluck('id', 'name');
        foreach ([
            'Telefon' => $school->tel,
            'Mobile' => $school->handy,
            'Email' => $school->email,
        ] as $type => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            $contactTypeId = $contactTypes[$type] ?? null;
            if (! $contactTypeId) {
                throw new RuntimeException("Kontakttyp {$type} fehlt in ZBB.");
            }
            DB::table('kontaktes')->updateOrInsert(
                [
                    'model_type' => 'App\\Models\\Personen',
                    'model_id' => $personId,
                    'kontakttyp_id' => $contactTypeId,
                ],
                [
                    'wert' => Str::limit($value, 100, ''),
                    'bemerkung' => 'Aus BOP-Schule migriert',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        DB::table('partner_has_partnerschaftstypens')->updateOrInsert(
            ['partner_id' => $partnerId, 'partnerschaftstypen_id' => $partnershipTypeId],
            [
                'ansprechpartner_id' => $personId,
                'rolle' => 'Ansprechpartner',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $partnerTypePivotId = DB::table('partner_has_partnerschaftstypens')
            ->where('partner_id', $partnerId)
            ->where('partnerschaftstypen_id', $partnershipTypeId)
            ->where('ansprechpartner_id', $personId)
            ->value('id');

        DB::table('projekt_has_ansprechpartners')->updateOrInsert(
            ['projekt_id' => $projectId, 'ansprechpartner_id' => $partnerTypePivotId],
            [
                'partnerschaftstypen_id' => $partnershipTypeId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->storeMapping($runId, 'schules_ansprechpartner', (string) $school->id, 'personens', (int) $personId, $this->checksum($payload));
        $this->storeSnapshot($runId, 'schules_ansprechpartner', (string) $school->id, $payload, 'imported', null);
    }

    private function importParticipants(int $runId, int $projectId, int $locationId, array $schoolMap, array &$summary): array
    {
        $map = [];
        $studentMap = [];
        foreach ($this->source()->table('teilnehmers')->orderBy('id')->cursor() as $participant) {
            $payload = (array) $participant;
            $checksum = $this->checksum($payload);
            $personId = $this->mappedTargetId('teilnehmers', (string) $participant->id, 'personens');

            $personValues = [
                'vorname' => $participant->vorname,
                'nachname' => $participant->nachname,
                'geburtsdatum' => $participant->geburtsdatum,
                'geschlecht' => $participant->geschlecht,
                'aktiv' => true,
                'typ' => 'teilnehmer',
                'updated_at' => $participant->updated_at ?? now(),
            ];

            if ($personId) {
                DB::table('personens')->where('id', $personId)->update($personValues);
            } else {
                $personId = DB::table('personens')->insertGetId($personValues + [
                    'created_at' => $participant->created_at ?? now(),
                ]);
            }

            $schoolId = $schoolMap[(int) $participant->schule_id] ?? null;
            if (! $schoolId) {
                throw new RuntimeException("Keine Schulzuordnung fuer BOP-Teilnehmer {$participant->id}.");
            }

            DB::table('personen_ist_schuelers')->updateOrInsert(
                [
                    'person_id' => $personId,
                    'schule_id' => $schoolId,
                    'schuljahr' => $participant->schuljahr,
                    'teil' => $participant->teil,
                    'klasse' => $participant->klasse,
                ],
                [
                    'foerderschueler' => false,
                    'eee' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $studentId = DB::table('personen_ist_schuelers')
                ->where('person_id', $personId)
                ->where('schule_id', $schoolId)
                ->where('schuljahr', $participant->schuljahr)
                ->where('teil', $participant->teil)
                ->where('klasse', $participant->klasse)
                ->value('id');

            DB::table('standort_has_personens')->updateOrInsert(
                ['personen_id' => $personId, 'standort_id' => $locationId],
                ['created_at' => now(), 'updated_at' => now()]
            );

            DB::table('projekt_has_personens')->updateOrInsert(
                ['personen_id' => $personId, 'projekt_id' => $projectId],
                [
                    'status' => 'aktiv',
                    'standort_id' => $locationId,
                    'bemerkung' => 'Aus BOP migriert',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $this->storeMapping($runId, 'teilnehmers', (string) $participant->id, 'personens', $personId, $checksum);
            $this->storeSnapshot($runId, 'teilnehmers', (string) $participant->id, $payload, 'partially_imported', 'Adresse, Einwilligung, BOP-Status und Auswertungsverweise bleiben bis zum Fachmapping im Snapshot erhalten.');
            $map[(int) $participant->id] = $personId;
            $studentMap[(int) $participant->id] = (int) $studentId;
            $summary['participants_imported']++;
        }

        return ['persons' => $map, 'students' => $studentMap];
    }

    private function importAreas(int $runId, int $projectId, array &$summary): array
    {
        $map = [];

        foreach ($this->source()->table('bereiches')->orderBy('id')->cursor() as $area) {
            $payload = (array) $area;
            $areaId = $this->mappedTargetId('bereiches', (string) $area->id, 'bereiches');
            $values = [
                'name' => Str::limit($area->name, 30, ''),
                'beschreibung' => $area->beschreibung ? Str::limit($area->beschreibung, 200, '') : null,
                'code' => $area->abkuerzung,
                'aktiv' => true,
                'updated_at' => $area->updated_at ?? now(),
            ];

            if ($areaId) {
                DB::table('bereiches')->where('id', $areaId)->update($values);
            } else {
                $areaId = DB::table('bereiches')->whereRaw('LOWER(name) = ?', [Str::lower($values['name'])])->value('id');
                if ($areaId) {
                    DB::table('bereiches')->where('id', $areaId)->update($values);
                } else {
                    $areaId = DB::table('bereiches')->insertGetId($values + ['created_at' => $area->created_at ?? now()]);
                }
            }

            DB::table('projekt_has_bereiches')->updateOrInsert(
                ['projekt_id' => $projectId, 'bereich_id' => $areaId],
                []
            );
            $this->storeMapping($runId, 'bereiches', (string) $area->id, 'bereiches', (int) $areaId, $this->checksum($payload));
            $this->storeSnapshot($runId, 'bereiches', (string) $area->id, $payload, 'imported', null);
            $map[(int) $area->id] = (int) $areaId;
            $summary['areas_imported']++;
        }

        return $map;
    }

    private function importLegacyStaff(int $runId): array
    {
        $map = [];

        foreach ($this->source()->table('users')->orderBy('id')->cursor() as $legacyUser) {
            $payload = (array) $legacyUser;
            $personId = DB::table('users')->whereRaw('LOWER(email) = ?', [Str::lower($legacyUser->email)])->value('person_id');
            $personId ??= $this->mappedTargetId('users', (string) $legacyUser->id, 'personens');

            if (! $personId) {
                [$firstName, $lastName] = $this->splitName($legacyUser->name);
                $personId = DB::table('personens')->insertGetId([
                    'vorname' => $firstName,
                    'nachname' => $lastName,
                    'geschlecht' => 'd',
                    'geburtsdatum' => null,
                    'aktiv' => false,
                    'typ' => 'mitarbeiter',
                    'created_at' => $legacyUser->created_at ?? now(),
                    'updated_at' => $legacyUser->updated_at ?? now(),
                ]);
            }

            $this->storeMapping($runId, 'users', (string) $legacyUser->id, 'personens', (int) $personId, $this->checksum($payload));
            $this->storeSnapshot($runId, 'users', (string) $legacyUser->id, $payload, 'partially_imported', 'Nicht gematchte Legacy-Benutzer werden als inaktive Mitarbeiter ohne Login erhalten.');
            $map[(int) $legacyUser->id] = (int) $personId;
        }

        return $map;
    }

    private function importGroups(int $runId, int $projectId, int $locationId, array $areaMap, array $staffMap, array $schoolMap, array &$summary): array
    {
        $map = [];
        $schools = $this->source()->table('schules')->pluck('schule', 'id');

        foreach ($this->source()->table('gruppes')->orderBy('id')->cursor() as $group) {
            $payload = (array) $group;
            $groupId = $this->mappedTargetId('gruppes', (string) $group->id, 'gruppes');
            $areaId = $areaMap[(int) $group->bereich_id] ?? null;
            $staffId = $staffMap[(int) $group->user_id] ?? null;
            if (! $areaId || ! $staffId || ! isset($schoolMap[(int) $group->schule_id])) {
                throw new RuntimeException("Unvollstaendiges Mapping fuer BOP-Gruppe {$group->id}.");
            }

            $values = [
                'personen_id' => $staffId,
                'bereich_id' => $areaId,
                'projekt_id' => $projectId,
                'standort_id' => $locationId,
                'ort_typ' => 'extern',
                'raum_id' => null,
                'externer_ort' => Str::limit((string) ($schools[(int) $group->schule_id] ?? self::LOCATION_NAME), 255, ''),
                'anfangsdatum' => $group->anfangsdatum,
                'enddatum' => $group->enddatum,
                'startzeit' => '08:00:00',
                'endzeit' => '17:00:00',
                'bemerkung' => 'Aus BOP migriert; BOP-Gruppe '.$group->id,
                'updated_at' => $group->updated_at ?? now(),
            ];

            if ($groupId) {
                DB::table('gruppes')->where('id', $groupId)->update($values);
            } else {
                $groupId = DB::table('gruppes')->insertGetId($values + ['created_at' => $group->created_at ?? now()]);
            }

            $this->storeMapping($runId, 'gruppes', (string) $group->id, 'gruppes', (int) $groupId, $this->checksum($payload));
            $this->storeSnapshot($runId, 'gruppes', (string) $group->id, $payload, 'imported', 'Schulbezug ist als externer Ort und ueber Teilnehmer-Schulhistorie erhalten.');
            $map[(int) $group->id] = (int) $groupId;
            $summary['groups_imported']++;
        }

        return $map;
    }

    private function importGroupAttendance(int $runId, array $personMap, array $groupMap, array $staffMap, array &$summary): void
    {
        $presentStatusId = DB::table('anwesenheitsstatutens')->where('status', 'anwesend')->value('id');
        $absentStatusId = DB::table('anwesenheitsstatutens')->where('status', 'unentschuldigt')->value('id');
        $plannedTimeId = DB::table('zeitens')->where('startzeit', '08:00:00')->where('endzeit', '17:00:00')->value('id');
        if (! $presentStatusId || ! $absentStatusId || ! $plannedTimeId) {
            throw new RuntimeException('Anwesenheitsstatus oder Standardzeit 08:00-17:00 fehlt in ZBB.');
        }
        $fallbackActorUserId = DB::table('users')->orderBy('id')->value('id');
        if (! $fallbackActorUserId) {
            throw new RuntimeException('ZBB enthaelt keinen Benutzer, der als technischer Akteur der Anwesenheitsmigration verwendet werden kann.');
        }

        $query = $this->source()->table('gruppe_has_teilnehmer as membership')
            ->join('gruppes as legacy_group', 'legacy_group.id', '=', 'membership.gruppe_id')
            ->join('anwesenheitslistes as attendance', 'attendance.id', '=', 'membership.anwesenheitsliste_id')
            ->select([
                'membership.*', 'legacy_group.anfangsdatum', 'legacy_group.enddatum', 'legacy_group.user_id',
                'attendance.tag1', 'attendance.tag2', 'attendance.tag3',
            ])
            ->orderBy('membership.id');

        foreach ($query->cursor() as $membership) {
            $personId = $personMap[(int) $membership->teilnehmer_id] ?? null;
            $groupId = $groupMap[(int) $membership->gruppe_id] ?? null;
            $staffId = $staffMap[(int) $membership->user_id] ?? null;
            if (! $personId || ! $groupId || ! $staffId) {
                throw new RuntimeException("Unvollstaendiges Mapping fuer BOP-Gruppenzuordnung {$membership->id}.");
            }
            $actorUserId = $this->legacyUserTargetUserId($membership->user_id) ?? (int) $fallbackActorUserId;

            $dates = $this->workdays($membership->anfangsdatum, $membership->enddatum, 3);
            foreach (['tag1', 'tag2', 'tag3'] as $index => $field) {
                if (! isset($dates[$index])) {
                    $summary['attendance_conflicts']++;
                    continue;
                }

                $dayId = $this->ensureDay($dates[$index]);
                $statusId = (bool) $membership->{$field} ? $presentStatusId : $absentStatusId;
                DB::table('gruppe_has_personens')->updateOrInsert(
                    ['personen_id' => $personId, 'gruppe_id' => $groupId, 'tage_id' => $dayId],
                    [
                        'user_id' => $actorUserId,
                        'zeitgeplant_id' => $plannedTimeId,
                        'zeittatsaechlich_id' => (bool) $membership->{$field} ? $plannedTimeId : null,
                        'anwesenheitsstatuten_id' => $statusId,
                        'bemerkung' => "Aus BOP {$field}; Datum aus Gruppenbeginn als Arbeitstag rekonstruiert",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $summary['attendance_rows_imported']++;
            }

            $payload = (array) $membership;
            $this->storeSnapshot($runId, 'gruppe_has_teilnehmer', (string) $membership->id, $payload, 'partially_imported', 'Anwesenheitsdatum wurde aus dem Gruppenbeginn ueber Arbeitstage rekonstruiert; Bewertungsbogen folgt separat.');
        }
    }

    private function importSelectionsAndAssignments(int $runId, array $studentMap, array $areaMap, array &$summary): void
    {
        foreach ($this->source()->table('orientierungs')->orderBy('id')->cursor() as $selection) {
            $studentId = $studentMap[(int) $selection->teilnehmer_id] ?? null;
            if (! $studentId) {
                throw new RuntimeException("Kein Schueler-Mapping fuer BOP-Bereichswahl {$selection->id}.");
            }

            $values = [];
            foreach ([1, 2, 3, 4] as $priority) {
                $legacyAreaId = $selection->{'bereich_id'.$priority};
                $values['bereich_id'.$priority] = $legacyAreaId ? ($areaMap[(int) $legacyAreaId] ?? null) : null;
            }
            $values['user_create'] = $this->legacyUserTargetUserId($selection->user_create);
            $values['user_update'] = $this->legacyUserTargetUserId($selection->user_update);
            $values['created_at'] = $selection->created_at ?? now();
            $values['updated_at'] = $selection->updated_at ?? now();

            DB::table('bereichsauswahls')->updateOrInsert(['teilnehmer_id' => $studentId], $values);
            $payload = (array) $selection;
            $targetId = DB::table('bereichsauswahls')->where('teilnehmer_id', $studentId)->value('id');
            $this->storeMapping($runId, 'orientierungs', (string) $selection->id, 'bereichsauswahls', (int) $targetId, $this->checksum($payload));
            $this->storeSnapshot($runId, 'orientierungs', (string) $selection->id, $payload, 'imported', null);
            $summary['selections_imported']++;
        }

        foreach ($this->source()->table('einteilungs')->orderBy('id')->cursor() as $assignment) {
            $studentId = $studentMap[(int) $assignment->teilnehmer_id] ?? null;
            if (! $studentId) {
                throw new RuntimeException("Kein Schueler-Mapping fuer BOP-Einteilung {$assignment->id}.");
            }

            foreach ([1, 2, 3] as $round) {
                $legacyAreaId = $assignment->{'bereich_id'.$round};
                if (! $legacyAreaId) {
                    continue;
                }
                $areaId = $areaMap[(int) $legacyAreaId] ?? null;
                if (! $areaId) {
                    throw new RuntimeException("Kein Bereichs-Mapping fuer BOP-Einteilung {$assignment->id}, Runde {$round}.");
                }

                DB::table('einteilung_bereiches')->updateOrInsert(
                    [
                        'teilnehmende_type' => 'App\\Models\\PersonenIstSchueler',
                        'teilnehmende_id' => $studentId,
                        'runde' => $round,
                    ],
                    [
                        'bereich_id' => $areaId,
                        'created_at' => $assignment->created_at ?? now(),
                        'updated_at' => $assignment->updated_at ?? now(),
                    ]
                );
                $summary['assignments_imported']++;
            }

            $payload = (array) $assignment;
            $this->storeSnapshot($runId, 'einteilungs', (string) $assignment->id, $payload, 'imported', null);
        }
    }

    private function legacyUserTargetUserId(mixed $legacyUserId): ?int
    {
        if (! $legacyUserId) {
            return null;
        }

        $email = $this->source()->table('users')->where('id', $legacyUserId)->value('email');
        if (! $email) {
            return null;
        }

        $userId = DB::table('users')->whereRaw('LOWER(email) = ?', [Str::lower($email)])->value('id');

        return $userId ? (int) $userId : null;
    }

    private function importPotentialAnalysis(int $runId, int $projectId, array $personMap, array $groupMap, array &$summary): void
    {
        $exerciseMap = [];
        foreach ($this->source()->table('uebungs')->orderBy('id')->cursor() as $exercise) {
            $exerciseId = DB::table('potenzialanalyse_uebungen')
                ->where('projekt_id', $projectId)
                ->where('name', $exercise->name)
                ->value('id');
            $values = [
                'tag' => null,
                'beschreibung' => $exercise->beschreibung,
                'hoechstwert' => $exercise->hoechstwert,
                'auswertbar' => (bool) $exercise->auswertbar,
                'sort_order' => (int) $exercise->id,
                'aktiv' => true,
                'updated_at' => $exercise->updated_at ?? now(),
            ];
            if ($exerciseId) {
                DB::table('potenzialanalyse_uebungen')->where('id', $exerciseId)->update($values);
            } else {
                $exerciseId = DB::table('potenzialanalyse_uebungen')->insertGetId($values + [
                    'projekt_id' => $projectId,
                    'name' => $exercise->name,
                    'created_at' => $exercise->created_at ?? now(),
                ]);
            }
            $exerciseMap[(int) $exercise->id] = (int) $exerciseId;
        }

        $paLegacyGroups = $this->source()->table('gruppe_has_teilnehmer as membership')
            ->join('gruppes as legacy_group', 'legacy_group.id', '=', 'membership.gruppe_id')
            ->join('bereiches as area', 'area.id', '=', 'legacy_group.bereich_id')
            ->whereRaw("LOWER(area.name) LIKE '%potenzial%'")
            ->selectRaw('membership.teilnehmer_id, MIN(membership.gruppe_id) gruppe_id')
            ->groupBy('membership.teilnehmer_id')
            ->pluck('gruppe_id', 'teilnehmer_id');

        $traits = [
            'feinmotorik', 'grobmotorik', 'wahrnehmung_symmetrie', 'analyse_problemloesefaehigkeit',
            'arbeitsplanung', 'motivation_leistungsbereitschaft', 'durchhaltevermoegen', 'sorgfalt',
            'kommunikation', 'teamfaehigkeit', 'umgangsformen',
        ];

        foreach ($this->source()->table('teilnehmers')->where(function ($query) {
            $query->whereNotNull('auswertung_pa_id')->orWhereNotNull('selbsteinschaetzung_id');
        })->orderBy('id')->cursor() as $participant) {
            $personId = $personMap[(int) $participant->id] ?? null;
            $groupId = isset($paLegacyGroups[$participant->id]) ? ($groupMap[(int) $paLegacyGroups[$participant->id]] ?? null) : null;
            if (! $personId || ! $groupId) {
                throw new RuntimeException("PA-Gruppe fehlt fuer BOP-Teilnehmer {$participant->id}.");
            }

            foreach ([['auswertung_pas', $participant->auswertung_pa_id, 'anleiter'], ['selbsteinschaetzungs', $participant->selbsteinschaetzung_id, 'selbst']] as [$table, $sourceId, $type]) {
                if (! $sourceId) {
                    continue;
                }
                $rating = $this->source()->table($table)->where('id', $sourceId)->first();
                if (! $rating) {
                    throw new RuntimeException("Fehlende PA-Quelle {$table}:{$sourceId}.");
                }
                foreach ($traits as $trait) {
                    DB::table('potenzialanalyse_kompetenzbewertungen')->updateOrInsert(
                        ['gruppe_id' => $groupId, 'personen_id' => $personId, 'typ' => $type, 'merkmal' => $trait],
                        [
                            'user_id' => null,
                            'bewertung' => $rating->{$trait},
                            'bemerkung' => 'Aus BOP migriert',
                            'created_at' => $rating->created_at ?? now(),
                            'updated_at' => $rating->updated_at ?? now(),
                        ]
                    );
                    $summary['pa_ratings_imported']++;
                }
                $this->storeSnapshot($runId, $table, (string) $sourceId, (array) $rating, 'imported', null);
            }
        }

        foreach ($this->source()->table('teilnehmer_has_uebungens')->orderBy('id')->cursor() as $result) {
            $personId = $personMap[(int) $result->teilnehmer_id] ?? null;
            $groupId = isset($paLegacyGroups[$result->teilnehmer_id]) ? ($groupMap[(int) $paLegacyGroups[$result->teilnehmer_id]] ?? null) : null;
            $exerciseId = $exerciseMap[(int) $result->uebung_id] ?? null;
            if (! $personId || ! $groupId || ! $exerciseId) {
                throw new RuntimeException("Unvollstaendiges PA-Uebungsmapping fuer BOP-Ergebnis {$result->id}.");
            }
            DB::table('potenzialanalyse_uebung_ergebnisse')->updateOrInsert(
                ['gruppe_id' => $groupId, 'personen_id' => $personId, 'uebung_id' => $exerciseId],
                [
                    'user_id' => null,
                    'punkte' => $result->punkte,
                    'zeit' => $result->zeit,
                    'created_at' => $result->created_at ?? now(),
                    'updated_at' => $result->updated_at ?? now(),
                ]
            );
            $this->storeSnapshot($runId, 'teilnehmer_has_uebungens', (string) $result->id, (array) $result, 'imported', null);
            $summary['pa_exercise_results_imported']++;
        }
    }

    private function importBoRatings(int $runId, array $personMap, array $groupMap, array &$summary): void
    {
        $excluded = ['id', 'name', 'created_at', 'updated_at'];
        $query = $this->source()->table('gruppe_has_teilnehmer as membership')
            ->join('bewertungsbogens as rating', 'rating.id', '=', 'membership.bewertungsbogen_id')
            ->select(['membership.id as membership_id', 'membership.teilnehmer_id', 'membership.gruppe_id', 'rating.*'])
            ->orderBy('membership.id');

        foreach ($query->cursor() as $row) {
            $payload = (array) $row;
            $personId = $personMap[(int) $row->teilnehmer_id] ?? null;
            $groupId = $groupMap[(int) $row->gruppe_id] ?? null;
            if (! $personId || ! $groupId) {
                throw new RuntimeException("Unvollstaendiges BO-Mapping fuer Gruppenzuordnung {$row->membership_id}.");
            }

            foreach ($payload as $criterion => $value) {
                if (in_array($criterion, $excluded, true) || in_array($criterion, ['membership_id', 'teilnehmer_id', 'gruppe_id'], true)) {
                    continue;
                }
                DB::table('berufsorientierung_bewertungen')->updateOrInsert(
                    ['gruppe_id' => $groupId, 'personen_id' => $personId, 'kriterium' => $criterion],
                    [
                        'user_id' => null,
                        'bewertung' => $value,
                        'legacy_bewertungsbogen_id' => $row->id,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]
                );
                $summary['bo_ratings_imported']++;
            }

            $this->storeSnapshot($runId, 'bewertungsbogens', (string) $row->id, $payload, 'imported', null);
        }
    }

    private function workdays(string $start, string $end, int $limit): array
    {
        $date = CarbonImmutable::parse($start)->startOfDay();
        $endDate = CarbonImmutable::parse($end)->startOfDay();
        $holidaysByYear = [];
        $dates = [];

        while ($date->lte($endDate) && count($dates) < $limit) {
            $year = $date->year;
            $holidaysByYear[$year] ??= Yasumi::create('Germany', $year, 'de_DE', 'Saarland');
            if (! $date->isWeekend() && ! $holidaysByYear[$year]->isHoliday($date)) {
                $dates[] = $date->format('Y-m-d');
            }
            $date = $date->addDay();
        }

        return $dates;
    }

    private function ensureDay(string $date): int
    {
        $dayId = DB::table('tages')->where('datum', $date)->value('id');
        if ($dayId) {
            return (int) $dayId;
        }

        $day = CarbonImmutable::parse($date);
        $weekdays = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];

        return DB::table('tages')->insertGetId([
            'datum' => $date,
            'wochentag' => $weekdays[$day->dayOfWeekIso],
            'feiertag_typ' => 'kein_feiertag',
            'feiertag_name' => null,
        ]);
    }

    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [$parts[0] ?? 'BOP', $parts[1] ?? 'Legacy-Benutzer'];
    }

    private function mappedTargetId(string $sourceTable, string $sourceId, string $targetTable): ?int
    {
        $targetId = DB::table('legacy_id_mappings')
            ->where('source', self::SOURCE)
            ->where('source_table', $sourceTable)
            ->where('source_id', $sourceId)
            ->where('target_table', $targetTable)
            ->value('target_id');

        return $targetId ? (int) $targetId : null;
    }

    private function storeMapping(int $runId, string $sourceTable, string $sourceId, string $targetTable, int $targetId, string $checksum): void
    {
        DB::table('legacy_id_mappings')->updateOrInsert(
            [
                'source' => self::SOURCE,
                'source_table' => $sourceTable,
                'source_id' => $sourceId,
                'target_table' => $targetTable,
            ],
            [
                'legacy_import_run_id' => $runId,
                'target_id' => $targetId,
                'record_checksum' => $checksum,
                'status' => 'imported',
                'error_message' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function storeSnapshot(int $runId, string $sourceTable, string $sourceId, array $payload, string $classification, ?string $reason): void
    {
        DB::table('legacy_record_snapshots')->updateOrInsert(
            ['source' => self::SOURCE, 'source_table' => $sourceTable, 'source_id' => $sourceId],
            [
                'legacy_import_run_id' => $runId,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'record_checksum' => $this->checksum($payload),
                'classification' => $classification,
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function checksum(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function source(): ConnectionInterface
    {
        return DB::connection('legacy_bop');
    }

    private function assertSafeSource(ConnectionInterface $source): void
    {
        if ($source->getDatabaseName() === DB::connection()->getDatabaseName()) {
            throw new RuntimeException('BOP-Quelle und ZBB-Ziel duerfen nicht dieselbe Datenbank sein.');
        }
    }
}
