<?php

namespace App\Services\Participants;

use App\Models\Abschluesse;
use App\Models\PersonenHasAbschluesse;
use Illuminate\Support\Str;

class ImportedSchoolQualification
{
    public function store(int $personId, ?string $label): bool
    {
        $label = trim((string) $label);
        if ($label === '' || mb_strlen($label) > 255) {
            return false;
        }

        $key = fn (string $value) => Str::lower(Str::squish($value));
        $qualification = Abschluesse::where('typ', 'schule')->get()
            ->first(fn ($entry) => $key($entry->bezeichnung) === $key($label));
        $qualification ??= Abschluesse::create(['typ' => 'schule', 'bezeichnung' => $label]);

        if (PersonenHasAbschluesse::where('person_id', $personId)
            ->where('abschluss_id', $qualification->id)->exists()) {
            return false;
        }

        PersonenHasAbschluesse::create([
            'person_id' => $personId,
            'abschluss_id' => $qualification->id,
            'bezeichnung' => 'Schulabschluss bei Übermittlung durch BA laut Importdatei',
            'start' => null,
            'end' => null,
        ]);

        return true;
    }
}
