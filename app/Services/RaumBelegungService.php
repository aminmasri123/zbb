<?php

namespace App\Services;

use App\Models\Gruppe;
use App\Models\Raeume;
use App\Models\RaumBuchung;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class RaumBelegungService
{
    /**
     * Liefert alle Belegungen, die sich mit dem taeglich wiederkehrenden
     * Zeitfenster einer Gruppe ueberschneiden. Die strukturierten Daten werden
     * im Gruppenformular fuer die ausdrueckliche Doppelbelegungs-Bestaetigung
     * verwendet.
     */
    public function conflictsForGroup(
        int $raumId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $ignoreGruppeId = null
    ): array {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $startTime = $start->format('H:i:s');
        $endTime = $end->format('H:i:s');

        if ($endDate < $startDate || $endTime <= $startTime) {
            throw ValidationException::withMessages([
                'endzeit' => 'Das Ende muss nach dem Beginn liegen.',
            ]);
        }

        $raum = Raeume::query()->with('standort')->find($raumId);
        $room = $this->roomDetails($raumId, $raum);

        $buchungen = RaumBuchung::query()
            ->with(['gebuchtVonPerson', 'gebuchtVon.person', 'gruppe.bereich'])
            ->where('raum_id', $raumId)
            ->whereIn('status', ['reserviert', 'bestaetigt'])
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->orderBy('start_at')
            ->get()
            ->map(function (RaumBuchung $buchung) use ($start, $end, $room) {
                $overlap = $this->bookingOverlapWithDailySchedule(
                    $start,
                    $end,
                    $buchung->start_at,
                    $buchung->end_at,
                );

                if (! $overlap) {
                    return null;
                }

                $person = $buchung->gebuchtVonPerson;
                $supervisor = $person
                    ? trim($person->vorname . ' ' . $person->nachname)
                    : ($buchung->gebuchtVon?->name ?: null);
                $area = $buchung->gruppe?->bereich?->name;
                $label = $buchung->titel ?: ($area ?: ('Raumbuchung #' . $buchung->id));

                return [
                    'type' => 'booking',
                    'room' => $room,
                    'overlap' => $overlap,
                    'occupied_by' => [
                        'id' => $buchung->id,
                        'label' => $label,
                        'supervisor' => $supervisor,
                        'area' => $area,
                        'schools' => [],
                        'participant_count' => $buchung->teilnehmerzahl,
                        'period_label' => $this->dateTimePeriodLabel($buchung->start_at, $buchung->end_at),
                    ],
                ];
            })
            ->filter()
            ->values();

        $gruppen = Gruppe::query()
            ->with(['bereich', 'betreuer', 'partners', 'partner'])
            ->withCount('teilnehmer')
            ->where('raum_id', $raumId)
            ->where('ort_typ', 'raum')
            ->whereNotNull('anfangsdatum')
            ->whereNotNull('startzeit')
            ->whereNotNull('endzeit')
            ->when($ignoreGruppeId, fn ($query) => $query->whereKeyNot($ignoreGruppeId))
            ->whereDate('anfangsdatum', '<=', $endDate)
            ->where(function ($query) use ($startDate) {
                $query->whereDate('enddatum', '>=', $startDate)
                    ->orWhere(function ($query) use ($startDate) {
                        $query->whereNull('enddatum')
                            ->whereDate('anfangsdatum', '>=', $startDate);
                    });
            })
            ->where('startzeit', '<', $endTime)
            ->where('endzeit', '>', $startTime)
            ->orderBy('anfangsdatum')
            ->orderBy('startzeit')
            ->get()
            ->map(function (Gruppe $gruppe) use ($startDate, $endDate, $startTime, $endTime, $room) {
                $groupStartDate = Carbon::parse($gruppe->anfangsdatum)->toDateString();
                $groupEndDate = Carbon::parse($gruppe->enddatum ?: $gruppe->anfangsdatum)->toDateString();
                $groupStartTime = Carbon::parse($gruppe->startzeit)->format('H:i:s');
                $groupEndTime = Carbon::parse($gruppe->endzeit)->format('H:i:s');
                $overlapStartDate = max($startDate, $groupStartDate);
                $overlapEndDate = min($endDate, $groupEndDate);
                $overlapStartTime = max($startTime, $groupStartTime);
                $overlapEndTime = min($endTime, $groupEndTime);
                $supervisor = $gruppe->betreuer
                    ? trim($gruppe->betreuer->vorname . ' ' . $gruppe->betreuer->nachname)
                    : null;
                $area = $gruppe->bereich?->name;
                $schools = $gruppe->partners
                    ->push($gruppe->partner)
                    ->filter()
                    ->pluck('name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'type' => 'group',
                    'room' => $room,
                    'overlap' => $this->dailyOverlapDetails(
                        $overlapStartDate,
                        $overlapEndDate,
                        $overlapStartTime,
                        $overlapEndTime,
                    ),
                    'occupied_by' => [
                        'id' => $gruppe->id,
                        'label' => collect([$supervisor, $area])->filter()->implode(' — ') ?: ('Gruppe #' . $gruppe->id),
                        'supervisor' => $supervisor,
                        'area' => $area,
                        'schools' => $schools,
                        'participant_count' => (int) $gruppe->teilnehmer_count,
                        'period_label' => $this->dailyPeriodLabel(
                            $groupStartDate,
                            $groupEndDate,
                            $groupStartTime,
                            $groupEndTime,
                        ),
                    ],
                ];
            });

        return $buchungen
            ->concat($gruppen)
            ->sortBy(fn (array $conflict) => $conflict['overlap']['date_from'] . ' ' . $conflict['overlap']['time_from'])
            ->values()
            ->all();
    }

    public function assertAvailable(
        int $raumId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $ignoreBuchungId = null,
        ?int $ignoreGruppeId = null
    ): void {
        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages([
                'end_at' => 'Das Ende muss nach dem Beginn liegen.',
            ]);
        }

        $buchung = $this->conflictingBuchung($raumId, $start, $end, $ignoreBuchungId);

        if ($buchung) {
            throw ValidationException::withMessages([
                'raum_id' => 'Der Raum ist in diesem Zeitraum bereits durch "' . $buchung->titel . '" belegt.',
            ]);
        }

        $gruppe = $this->conflictingGruppe($raumId, $start, $end, $ignoreGruppeId);

        if ($gruppe) {
            $label = $gruppe->bereich?->name ?? ('Gruppe #' . $gruppe->id);

            throw ValidationException::withMessages([
                'raum_id' => 'Der Raum ist in diesem Zeitraum bereits durch "' . $label . '" belegt.',
            ]);
        }
    }

    private function conflictingBuchung(
        int $raumId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $ignoreBuchungId
    ): ?RaumBuchung {
        return RaumBuchung::query()
            ->where('raum_id', $raumId)
            ->whereIn('status', ['reserviert', 'bestaetigt'])
            ->when($ignoreBuchungId, fn ($query) => $query->whereKeyNot($ignoreBuchungId))
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->orderBy('start_at')
            ->first();
    }

    private function conflictingGruppe(
        int $raumId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $ignoreGruppeId
    ): ?Gruppe {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $startTime = $start->format('H:i:s');
        $endTime = $end->format('H:i:s');

        return Gruppe::query()
            ->with('bereich')
            ->where('raum_id', $raumId)
            ->where('ort_typ', 'raum')
            ->whereNotNull('anfangsdatum')
            ->whereNotNull('startzeit')
            ->whereNotNull('endzeit')
            ->when($ignoreGruppeId, fn ($query) => $query->whereKeyNot($ignoreGruppeId))
            ->whereDate('anfangsdatum', '<=', $endDate)
            ->where(function ($query) use ($startDate) {
                $query->whereDate('enddatum', '>=', $startDate)
                    ->orWhere(function ($query) use ($startDate) {
                        $query->whereNull('enddatum')
                            ->whereDate('anfangsdatum', '>=', $startDate);
                    });
            })
            ->when($startDate === $endDate, function ($query) use ($startTime, $endTime) {
                $query->where('startzeit', '<', $endTime)
                    ->where('endzeit', '>', $startTime);
            })
            ->orderBy('anfangsdatum')
            ->orderBy('startzeit')
            ->first();
    }

    private function roomDetails(int $raumId, ?Raeume $raum): array
    {
        return [
            'id' => $raumId,
            'name' => $raum?->name ?: ('Raum #' . $raumId),
            'location' => $raum?->standort?->name,
        ];
    }

    private function bookingOverlapWithDailySchedule(
        CarbonInterface $scheduleStart,
        CarbonInterface $scheduleEnd,
        CarbonInterface $bookingStart,
        CarbonInterface $bookingEnd
    ): ?array {
        $from = Carbon::parse(max($scheduleStart->toDateString(), $bookingStart->toDateString()))->startOfDay();
        $to = Carbon::parse(min($scheduleEnd->toDateString(), $bookingEnd->toDateString()))->startOfDay();

        if ($from->gt($to)) {
            return null;
        }

        $first = $this->findDailyBookingOverlap(
            $from,
            $to,
            $scheduleStart,
            $scheduleEnd,
            $bookingStart,
            $bookingEnd,
            false,
        );

        if (! $first) {
            return null;
        }

        $last = $this->findDailyBookingOverlap(
            $from,
            $to,
            $scheduleStart,
            $scheduleEnd,
            $bookingStart,
            $bookingEnd,
            true,
        ) ?: $first;

        $sameTime = $first['start']->format('H:i:s') === $last['start']->format('H:i:s')
            && $first['end']->format('H:i:s') === $last['end']->format('H:i:s');

        if ($sameTime) {
            return $this->dailyOverlapDetails(
                $first['start']->toDateString(),
                $last['start']->toDateString(),
                $first['start']->format('H:i:s'),
                $first['end']->format('H:i:s'),
            );
        }

        return [
            'date_from' => $first['start']->toDateString(),
            'date_to' => $last['start']->toDateString(),
            'time_from' => $first['start']->format('H:i'),
            'time_to' => $last['end']->format('H:i'),
            'label' => $first['start']->format('d.m.Y, H:i') . ' Uhr bis ' . $last['end']->format('d.m.Y, H:i') . ' Uhr',
        ];
    }

    private function findDailyBookingOverlap(
        CarbonInterface $from,
        CarbonInterface $to,
        CarbonInterface $scheduleStart,
        CarbonInterface $scheduleEnd,
        CarbonInterface $bookingStart,
        CarbonInterface $bookingEnd,
        bool $backwards
    ): ?array {
        $date = Carbon::parse($backwards ? $to : $from);
        $step = $backwards ? -1 : 1;

        // Bei einem durchgehenden Termin kann nur der Randtag das taegliche
        // Zeitfenster verfehlen. Der unmittelbar folgende Tag ist entweder ein
        // voller Belegungstag oder bereits der andere Randtag.
        for ($attempt = 0; $attempt < 2 && $date->betweenIncluded($from, $to); $attempt++) {
            $windowStart = $date->copy()->setTimeFromTimeString($scheduleStart->format('H:i:s'));
            $windowEnd = $date->copy()->setTimeFromTimeString($scheduleEnd->format('H:i:s'));
            $overlapStart = $windowStart->greaterThan($bookingStart) ? $windowStart : Carbon::parse($bookingStart);
            $overlapEnd = $windowEnd->lessThan($bookingEnd) ? $windowEnd : Carbon::parse($bookingEnd);

            if ($overlapStart->lt($overlapEnd)) {
                return ['start' => $overlapStart, 'end' => $overlapEnd];
            }

            $date->addDays($step);
        }

        return null;
    }

    private function dailyOverlapDetails(string $dateFrom, string $dateTo, string $timeFrom, string $timeTo): array
    {
        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'time_from' => substr($timeFrom, 0, 5),
            'time_to' => substr($timeTo, 0, 5),
            'label' => $this->dailyPeriodLabel($dateFrom, $dateTo, $timeFrom, $timeTo),
        ];
    }

    private function dailyPeriodLabel(string $dateFrom, string $dateTo, string $timeFrom, string $timeTo): string
    {
        $dates = Carbon::parse($dateFrom)->format('d.m.Y');

        if ($dateTo !== $dateFrom) {
            $dates .= ' bis ' . Carbon::parse($dateTo)->format('d.m.Y');
        }

        return $dates . ', ' . substr($timeFrom, 0, 5) . '–' . substr($timeTo, 0, 5) . ' Uhr';
    }

    private function dateTimePeriodLabel(CarbonInterface $start, CarbonInterface $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->format('d.m.Y, H:i') . '–' . $end->format('H:i') . ' Uhr';
        }

        return $start->format('d.m.Y, H:i') . ' Uhr bis ' . $end->format('d.m.Y, H:i') . ' Uhr';
    }
}
