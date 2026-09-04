<?php

namespace App\Services\Bop;

use App\Models\BerufsorientierungBewertung;
use App\Models\Gruppe;
use App\Models\Personen;
use App\Models\PersonenIstSchueler;
use App\Models\Projekt;
use App\Services\BerufsorientierungAuswertungService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BopEvaluationExportService
{
    public function __construct(private readonly BerufsorientierungAuswertungService $evaluations) {}

    public function isWorkshopGroup(Gruppe $group): bool
    {
        return str_contains(mb_strtolower((string) $group->projekt?->name), 'bop')
            && mb_strtolower(trim((string) $group->bereich?->name)) !== 'potenzialanalyse';
    }

    public function config(Projekt $project): array
    {
        return $this->evaluations->config($project);
    }

    public function groupEntries(Gruppe $group, ?int $personId = null): Collection
    {
        $group->loadMissing([
            'teilnehmer.schueler.schule',
            'bereich',
            'betreuer',
            'projekt',
            'partner',
        ]);

        $context = $this->groupContext($group);
        $participants = $group->teilnehmer
            ->unique('id')
            ->when($personId !== null, fn (Collection $items) => $items->where('id', $personId))
            ->values();
        $ratings = $this->ratingsFor($participants->pluck('id'), collect([$group->id]));

        return $participants
            ->map(function (Personen $person) use ($group, $context, $ratings) {
                $student = $this->studentFor($person, $context);

                return $this->entry(
                    $group,
                    $person,
                    $student,
                    $ratings->get($group->id.'|'.$person->id, collect())
                );
            })
            ->filter(fn (array $entry) => $entry['ratings']->isNotEmpty())
            ->sort($this->entryComparator(...))
            ->values();
    }

    public function schoolEntries(
        int $schoolId,
        string $schoolYear,
        string $part,
        Projekt $project
    ): Collection {
        $students = PersonenIstSchueler::query()
            ->with(['person', 'schule'])
            ->where('schule_id', $schoolId)
            ->forSchuljahr($schoolYear)
            ->where('teil', $part)
            ->get()
            ->filter(fn (PersonenIstSchueler $student) => $student->person !== null)
            ->unique('person_id')
            ->sort($this->studentComparator(...))
            ->values();

        if ($students->isEmpty()) {
            return collect();
        }

        $personIds = $students->pluck('person_id')->map(fn ($id) => (int) $id)->values();
        $groups = Gruppe::query()
            ->with(['teilnehmer', 'bereich', 'betreuer', 'projekt', 'partner'])
            ->where('projekt_id', $project->id)
            ->whereNotNull('bereich_id')
            ->where(function ($query) use ($schoolId) {
                $query->whereNull('partner_id')->orWhere('partner_id', $schoolId);
            })
            ->whereHas('teilnehmer', fn ($query) => $query->whereIn('personens.id', $personIds))
            ->get()
            ->filter(fn (Gruppe $group) => $this->isWorkshopGroup($group))
            ->values();
        $ratings = $this->ratingsFor($personIds, $groups->pluck('id'));

        return $students
            ->flatMap(function (PersonenIstSchueler $student) use ($groups, $ratings) {
                return $groups
                    ->filter(fn (Gruppe $group) => $group->teilnehmer->contains('id', $student->person_id))
                    ->sort(function (Gruppe $left, Gruppe $right) {
                        $area = strnatcasecmp((string) $left->bereich?->name, (string) $right->bereich?->name);

                        return $area !== 0 ? $area : ($left->id <=> $right->id);
                    })
                    ->map(fn (Gruppe $group) => $this->entry(
                        $group,
                        $student->person,
                        $student,
                        $ratings->get($group->id.'|'.$student->person_id, collect())
                    ))
                    ->filter(fn (array $entry) => $entry['ratings']->isNotEmpty());
            })
            ->values();
    }

    private function ratingsFor(Collection $personIds, Collection $groupIds): Collection
    {
        if ($personIds->isEmpty() || $groupIds->isEmpty()) {
            return collect();
        }

        return BerufsorientierungBewertung::query()
            ->whereIn('gruppe_id', $groupIds)
            ->whereIn('personen_id', $personIds)
            ->get()
            ->groupBy(fn (BerufsorientierungBewertung $rating) => $rating->gruppe_id.'|'.$rating->personen_id)
            ->map(fn (Collection $items) => $items->keyBy('kriterium'));
    }

    private function entry(
        Gruppe $group,
        Personen $person,
        ?PersonenIstSchueler $student,
        Collection $ratings
    ): array {
        return [
            'gruppe_id' => (int) $group->id,
            'personen_id' => (int) $person->id,
            'vorname' => (string) ($person->vorname ?? ''),
            'nachname' => (string) ($person->nachname ?? ''),
            'klasse' => trim((string) ($student?->klasse ?? '')),
            'datum' => $this->formatDate($group->enddatum ?: $group->anfangsdatum),
            'anleiter_name' => trim((string) ($group->betreuer?->vorname ?? '').' '.(string) ($group->betreuer?->nachname ?? '')),
            'schule_name' => (string) ($student?->schule?->name ?? $group->partner?->name ?? ''),
            'bereich_name' => (string) ($group->bereich?->name ?? ''),
            'ratings' => $ratings,
        ];
    }

    private function studentFor(Personen $person, array $context): ?PersonenIstSchueler
    {
        $students = $person->schueler ?? collect();

        return $students->first(function (PersonenIstSchueler $student) use ($context) {
            if (! empty($context['partner_id']) && (int) $student->schule_id !== (int) $context['partner_id']) {
                return false;
            }
            if (! empty($context['school_year']) && ! $this->sameSchoolYear($student->schuljahr, $context['school_year'])) {
                return false;
            }
            if (! empty($context['part']) && (string) $student->teil !== (string) $context['part']) {
                return false;
            }

            return true;
        }) ?: $students->last();
    }

    private function groupContext(Gruppe $group): array
    {
        $context = [
            'partner_id' => $group->partner_id ?: null,
            'school_year' => null,
            'part' => null,
        ];

        if (preg_match('/BOP Einteilung Schule\s+(\d+)\s+Schuljahr\s+(.+?)\s+Teil\s+(.+?)\s+Runde\s+\d+/u', (string) $group->bemerkung, $matches)) {
            $context['partner_id'] = (int) $matches[1];
            $context['school_year'] = trim($matches[2]);
            $context['part'] = trim($matches[3]);
        }

        return $context;
    }

    private function sameSchoolYear(?string $left, ?string $right): bool
    {
        preg_match('/\d{4}/', (string) $left, $leftMatch);
        preg_match('/\d{4}/', (string) $right, $rightMatch);

        return ($leftMatch[0] ?? trim((string) $left)) === ($rightMatch[0] ?? trim((string) $right));
    }

    private function entryComparator(array $left, array $right): int
    {
        foreach (['klasse', 'nachname', 'vorname', 'bereich_name'] as $key) {
            $comparison = strnatcasecmp((string) $left[$key], (string) $right[$key]);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return $left['personen_id'] <=> $right['personen_id'];
    }

    private function studentComparator(PersonenIstSchueler $left, PersonenIstSchueler $right): int
    {
        $class = strnatcasecmp((string) $left->klasse, (string) $right->klasse);
        if ($class !== 0) {
            return $class;
        }

        $lastName = strnatcasecmp((string) $left->person?->nachname, (string) $right->person?->nachname);
        if ($lastName !== 0) {
            return $lastName;
        }

        $firstName = strnatcasecmp((string) $left->person?->vorname, (string) $right->person?->vorname);

        return $firstName !== 0 ? $firstName : ($left->person_id <=> $right->person_id);
    }

    private function formatDate($value): string
    {
        if (! $value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d.m.Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
