<?php

namespace App\Services\Aptitude;

use App\Models\{AptitudeAttempt, AptitudeProfile, Bereich, Gruppe, Projekt};
use Illuminate\Validation\ValidationException;

class AptitudeGroupSetup
{
    public const AREA_CODE = 'E-TEST';

    public function ensureArea(Projekt $project): Bereich
    {
        $area = Bereich::where('code', self::AREA_CODE)->first()
            ?? Bereich::whereRaw('LOWER(TRIM(name)) = ?', ['eignungstest'])->first()
            ?? new Bereich(['name' => 'Eignungstest']);
        $area->fill(['code' => self::AREA_CODE, 'aktiv' => true])->save();
        $project->bereiche()->syncWithoutDetaching([$area->id => ['aktiv' => true]]);

        return $area;
    }

    public function profileForArea(Projekt $project, int $areaId, ?Gruppe $group = null): ?int
    {
        // The caller locks an existing group so changing its area cannot race with a saved test.
        if ($group && (int) $group->bereich_id !== $areaId
            && AptitudeAttempt::where('gruppe_id', $group->id)->exists()) {
            throw ValidationException::withMessages([
                'bereich' => 'Für diese Gruppe liegen bereits Testauswertungen vor. Der Bereich kann deshalb nicht geändert werden.',
            ]);
        }

        if (! Bereich::whereKey($areaId)->where('code', self::AREA_CODE)->exists()) {
            return null;
        }

        // Keep the original assessment criteria when an existing test group is edited.
        if ($group?->aptitude_profile_id) {
            return (int) $group->aptitude_profile_id;
        }

        if (! $project->featureEnabled('aptitude_tests')) {
            throw ValidationException::withMessages(['bereich' => 'Eignungstests sind in diesem Projekt deaktiviert.']);
        }

        $profile = AptitudeProfile::where('projekt_id', $project->id)->latest('version')->first();
        if (! $profile) {
            throw ValidationException::withMessages([
                'bereich' => 'Bitte zuerst unter Projektkonfiguration → Eignungstests die Testvorlage speichern.',
            ]);
        }

        return (int) $profile->id;
    }
}
