<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 10px; line-height: 1.35; }
        h1 { margin: 0 0 4px; font-size: 19px; color: #173f4f; }
        h2 { margin: 18px 0 7px; padding: 6px 8px; font-size: 12px; color: #173f4f; background: #edf5f7; border-left: 4px solid #2d7184; }
        .meta { margin-bottom: 14px; color: #4b5563; }
        .meta strong { color: #111827; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 9px; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; font-weight: bold; }
        .center { text-align: center; width: 70px; }
        .muted { color: #6b7280; }
        .report { white-space: pre-line; border: 1px solid #d1d5db; padding: 9px; min-height: 80px; }
        .signatures { margin-top: 28px; width: 100%; }
        .signature { width: 46%; display: inline-block; border-top: 1px solid #374151; padding-top: 4px; }
        .signature.right { float: right; }
    </style>
</head>
<body>
    <h1>{{ $berichtConfig['titel'] ?? 'Auswertung der Potenzialanalyse' }}</h1>
    @if(!empty($berichtConfig['untertitel']))
        <div class="muted">{{ $berichtConfig['untertitel'] }}</div>
    @endif
    <div class="meta">
        <strong>{{ $person->vorname }} {{ $person->nachname }}</strong><br>
        Projekt: {{ $gruppe->projekt?->name ?? '-' }} · Zeitraum: {{ $zeitraum ?: '-' }} · Erstellt: {{ $erstelltAm }}
    </div>

    @foreach($merkmale as $bereich => $items)
        <h2>{{ $bereich }}</h2>
        <table>
            <thead>
                <tr>
                    <th>Kompetenz</th>
                    @if($berichtConfig['selbsteinschaetzung_anzeigen'] ?? true)<th class="center">Selbst</th>@endif
                    <th class="center">Fremd</th>
                    <th>Beobachtung</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>{{ $item['label'] }}</td>
                        @if($berichtConfig['selbsteinschaetzung_anzeigen'] ?? true)<td class="center">{{ $item['selbst'] ?? '-' }}</td>@endif
                        <td class="center">{{ $item['anleiter'] ?? '-' }}</td>
                        <td>{{ $item['anleiter_bemerkung'] ?: ($item['selbst_bemerkung'] ?: '-') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    @if(($berichtConfig['uebungsergebnisse_anzeigen'] ?? true) && $uebungen->isNotEmpty())
        <h2>Übungsergebnisse</h2>
        <table>
            <thead><tr><th>Übung</th><th class="center">Punkte</th><th class="center">Fehler</th><th class="center">Zeit</th></tr></thead>
            <tbody>
                @foreach($uebungen as $uebung)
                    <tr>
                        <td>{{ $uebung['name'] }}</td>
                        <td class="center">{{ $uebung['punkte'] ?? '-' }}@if($uebung['hoechstwert']) / {{ $uebung['hoechstwert'] }}@endif</td>
                        <td class="center">{{ $uebung['fehler'] ?? '-' }}</td>
                        <td class="center">{{ $uebung['zeit'] ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($berichtConfig['staerkenprofil_anzeigen'] ?? true)
        <h2>Stärken und Zusammenfassung</h2>
        <div class="report">{{ $bericht?->bericht_text ?: 'Noch keine Zusammenfassung hinterlegt.' }}</div>
    @endif

    <div class="signatures">
        <div class="signature">Teilnehmer/in</div>
        <div class="signature right">Beobachter/in</div>
    </div>
</body>
</html>
