<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>PA-Bericht {{ $person->vorname }} {{ $person->nachname }}</title>
    <style>
        @page { margin: 14mm 14mm 13mm; }
        body { margin: 0; color: #17233a; font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; line-height: 1.35; }
        h1 { margin: 0; color: #0b3555; font-size: 20pt; }
        h2 { margin: 7mm 0 2.5mm; padding-bottom: 1.3mm; border-bottom: 2px solid #ff6b13; color: #0b3555; font-size: 12pt; }
        h3 { margin: 4mm 0 1.5mm; color: #0b3555; font-size: 9.5pt; }
        p { margin: 0 0 2.5mm; }
        .header { width: 100%; margin-bottom: 5mm; border-collapse: collapse; }
        .header td { vertical-align: bottom; }
        .brand { color: #ff6b13; text-align: right; font-size: 22pt; font-weight: 700; }
        .subtitle { margin-top: 1mm; color: #607089; font-size: 9pt; }
        .meta, .profile, .results, .criteria { width: 100%; border-collapse: collapse; }
        .meta td { border: 1px solid #cfd8e5; padding: 2.2mm 2.5mm; }
        .meta .label { width: 18%; background: #eef4f8; color: #526176; font-size: 8pt; }
        .meta .value { width: 32%; font-weight: 600; }
        .profile th, .profile td, .results th, .results td, .criteria th, .criteria td { border: 1px solid #cfd8e5; padding: 1.2mm 1.7mm; vertical-align: top; }
        .profile th, .results th, .criteria th { background: #0b3555; color: #fff; font-size: 8pt; text-align: left; }
        .profile .category td { background: #eef4f8; color: #0b3555; font-weight: 700; }
        .rating { width: 28mm; white-space: nowrap; text-align: center; }
        .dot { display: inline-block; width: 4.4mm; height: 4.4mm; margin-right: 0.7mm; border: 1px solid #8a99ac; border-radius: 50%; color: #fff; font-size: 6.8pt; line-height: 4.4mm; text-align: center; }
        .dot.active { border-color: #ff6b13; background: #ff6b13; }
        .muted { color: #8a99ac; font-size: 7.8pt; }
        .report-box { margin-bottom: 3mm; padding: 3mm; border: 1px solid #cfd8e5; border-left: 3px solid #ff6b13; background: #fbfcfd; }
        .report-label { margin-bottom: 1mm; color: #0b3555; font-weight: 700; }
        .report-text { white-space: normal; }
        .status { display: inline-block; padding: 1mm 2.5mm; border-radius: 8px; background: #eef4f8; color: #0b3555; font-size: 8pt; font-weight: 700; }
        .legend { margin-top: 2mm; color: #607089; font-size: 7.5pt; }
        .report-title { margin-top: 8mm; page-break-after: avoid; }
        .avoid-break { page-break-inside: avoid; }
        .footer { margin-top: 7mm; padding-top: 2mm; border-top: 1px solid #cfd8e5; color: #7a8799; font-size: 7.5pt; text-align: right; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>Bericht zur Potenzialanalyse</h1>
                <div class="subtitle">Berufsorientierungsprogramm (BOP)</div>
            </td>
            <td class="brand">ZBB</td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="label">Teilnehmer/in</td>
            <td class="value">{{ trim(($person->vorname ?? '') . ' ' . ($person->nachname ?? '')) }}</td>
            <td class="label">Geburtsdatum</td>
            <td class="value">{{ $person->geburtsdatum ? \Carbon\Carbon::parse($person->geburtsdatum)->format('d.m.Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Schule</td>
            <td class="value">{{ $school?->name ?: '-' }}</td>
            <td class="label">Klasse</td>
            <td class="value">{{ $student?->klasse ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Zeitraum PA</td>
            <td class="value">{{ $zeitraum ?: '-' }}</td>
            <td class="label">Berichtsstatus</td>
            <td class="value"><span class="status">{{ $statusLabel }}</span></td>
        </tr>
    </table>

    <h2>Kompetenzprofil</h2>
    <table class="profile">
        <thead>
            <tr>
                <th>Kompetenz</th>
                <th class="rating">Selbsteinschätzung</th>
                <th class="rating">Fremdeinschätzung</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($merkmale as $bereich => $eintraege)
                <tr class="category"><td colspan="3">{{ $bereich }}</td></tr>
                @foreach ($eintraege as $eintrag)
                    <tr>
                        <td>
                            {{ $eintrag['label'] }}
                            @if ($eintrag['anleiter_bemerkung'] || $eintrag['selbst_bemerkung'])
                                <div class="muted">{{ $eintrag['anleiter_bemerkung'] ?: $eintrag['selbst_bemerkung'] }}</div>
                            @endif
                        </td>
                        @foreach (['selbst', 'anleiter'] as $typ)
                            <td class="rating">
                                @for ($wert = 1; $wert <= 5; $wert++)
                                    <span class="dot {{ (int) $eintrag[$typ] === $wert ? 'active' : '' }}">{{ $wert }}</span>
                                @endfor
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
    <div class="legend">Skala: 1 = im entwicklungsfähigen Maße vorhanden - 5 = im höchsten Maße vorhanden</div>

    @if ($uebungen->isNotEmpty())
        <div class="avoid-break">
            <h2>Übungsergebnisse</h2>
            <table class="results">
                <thead>
                    <tr><th>Übung</th><th>Tag</th><th>Punkte</th><th>Zeit</th></tr>
                </thead>
                <tbody>
                    @foreach ($uebungen as $uebung)
                        <tr>
                            <td>{{ $uebung['name'] }}</td>
                            <td>{{ $uebung['tag'] ?: '-' }}</td>
                            <td>
                                {{ $uebung['punkte'] ?? '-' }}
                                @if ($uebung['hoechstwert'] !== null) / {{ $uebung['hoechstwert'] }} @endif
                            </td>
                            <td>{{ $uebung['zeit'] ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($kriterien->isNotEmpty())
        <div class="avoid-break">
            <h2>Weitere Beobachtungskriterien</h2>
            <table class="criteria">
                <thead>
                    <tr><th>Übung</th><th>Kriterium</th><th>Selbst</th><th>Fremd</th><th>Bemerkung</th></tr>
                </thead>
                <tbody>
                    @foreach ($kriterien as $kriterium)
                        <tr>
                            <td>{{ $kriterium['uebung'] ?: '-' }}</td>
                            <td>{{ $kriterium['kriterium'] }}</td>
                            <td>{{ $kriterium['selbst'] ?? '-' }}</td>
                            <td>{{ $kriterium['anleiter'] ?? '-' }}</td>
                            <td>{{ $kriterium['bemerkung'] ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h1 class="report-title">Persönlicher PA-Bericht</h1>
    <div class="subtitle">{{ trim(($person->vorname ?? '') . ' ' . ($person->nachname ?? '')) }} - erstellt am {{ $erstelltAm }}</div>

    <h2>Zusammenfassung</h2>
    <div class="report-box">
        <div class="report-text">
            @if (filled($bericht?->bericht_text))
                {!! nl2br(e($bericht->bericht_text)) !!}
            @else
                <span class="muted">Es wurde noch kein zusammenhängender Berichtstext gespeichert.</span>
            @endif
        </div>
    </div>

    <div class="report-box">
        <div class="report-label">Stärken</div>
        <div class="report-text">
            @if (filled($bericht?->staerken)) {!! nl2br(e($bericht->staerken)) !!} @else <span class="muted">Keine Angabe</span> @endif
        </div>
    </div>

    <div class="report-box">
        <div class="report-label">Entwicklungsfelder</div>
        <div class="report-text">
            @if (filled($bericht?->entwicklungsfelder)) {!! nl2br(e($bericht->entwicklungsfelder)) !!} @else <span class="muted">Keine Angabe</span> @endif
        </div>
    </div>

    <div class="report-box">
        <div class="report-label">Empfehlung</div>
        <div class="report-text">
            @if (filled($bericht?->empfehlung)) {!! nl2br(e($bericht->empfehlung)) !!} @else <span class="muted">Keine Angabe</span> @endif
        </div>
    </div>

    <div class="footer">BOP-Potenzialanalyse - {{ $school?->name ?: 'ZBB' }} - {{ $erstelltAm }}</div>
</body>
</html>
