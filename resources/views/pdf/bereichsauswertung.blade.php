<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 10px; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        h1 { font-size: 17px; margin: 0 0 5px; }
        .meta { color: #4b5563; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #9ca3af; padding: 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .score { width: 42px; text-align: center; font-weight: bold; }
        .legend { margin-top: 12px; color: #4b5563; }
        .missing { color: #b45309; }
    </style>
</head>
<body>
@foreach($teilnehmer as $person)
    @php($personRatings = $ratings->get($person['personen_id'], collect()))
    <section class="page">
        <h1>Auswertungsbogen Berufsorientierung</h1>
        <div class="meta">
            <strong>{{ $person['voller_name'] }}</strong><br>
            Schule: {{ $person['schule'] ?: '–' }} · Klasse: {{ $person['klasse'] ?: '–' }}<br>
            Bereich: {{ $person['bereich'] ?: '–' }} · Anleiter/in: {{ $person['anleiter'] ?: '–' }}<br>
            Zeitraum: {{ $person['anfangsdatum'] ?: '–' }} bis {{ $person['enddatum'] ?: '–' }}
        </div>
        <table>
            <thead><tr><th>Beobachtungspunkt</th><th class="score">Stufe</th><th>Bewertung / Bemerkung</th></tr></thead>
            <tbody>
            @foreach($config['criteria'] as $criterion)
                @php($rating = $personRatings->get($criterion['key']))
                <tr>
                    <td><strong>{{ $criterion['label'] }}</strong>@if($criterion['description'])<br><span>{{ $criterion['description'] }}</span>@endif</td>
                    <td class="score">{{ $rating?->bewertung ?: '–' }}</td>
                    <td class="{{ $rating?->bewertung ? '' : 'missing' }}">
                        {{ $rating?->bewertung ? ($config['scale'][$rating->bewertung] ?? '') : 'Noch nicht bewertet' }}
                        @if($rating?->bemerkung)<br>{{ $rating->bemerkung }}@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="legend">
            @foreach($config['scale'] as $value => $label)<strong>{{ $value }}</strong> = {{ $label }}@if(!$loop->last) · @endif @endforeach
        </div>
    </section>
@endforeach
</body>
</html>
