<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Abschlussbericht Potenzialanalyse</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm 15mm;
        }

        body {
            width: 18cm;
            margin: 0;
            padding: 0;
            color: #000;
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.2;
        }

        .report-header {
            width: 100%;
            margin: 0 0 8px;
            border: 0;
            border-collapse: collapse;
        }

        .report-header td {
            padding: 0;
            border: 0;
            vertical-align: middle;
        }

        h1 {
            margin: 0;
            font-size: 32pt;
            font-weight: bold;
            line-height: 1;
        }

        .logo-cell {
            width: 2.8cm;
            text-align: right;
        }

        .logo {
            width: auto;
            height: 2.1cm;
        }

        .report-table {
            width: 100%;
            margin: 0 0 8px;
            border-collapse: collapse;
            font-size: 9.5pt;
        }

        .report-table th,
        .report-table td {
            padding: 3px;
            border: 1px solid #000;
            color: #000;
            text-align: left;
            vertical-align: top;
            line-height: 1.2;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .report-table th {
            background-color: #cdcdcd;
            font-weight: normal;
        }

        .report-table .section-title,
        .report-table .category-title {
            font-weight: bold;
        }

        .report-table .column-title {
            background-color: #efefef;
            font-weight: bold;
        }

        .competency-name {
            width: 5.7cm;
            vertical-align: middle !important;
        }

        .assessment-source,
        .rating-cell,
        .rating-heading {
            width: 0.5cm;
            text-align: center !important;
            vertical-align: middle !important;
        }

        .assessment-source {
            width: 0.7cm;
            font-weight: bold;
            white-space: nowrap;
        }

        .assessment-text {
            vertical-align: middle !important;
        }

        .assessment-note {
            display: block;
            margin-top: 3px;
            font-size: 8.5pt;
            font-style: italic;
        }

        .competency-pair {
            page-break-inside: avoid;
        }

        .competency-section,
        .competency-category-table,
        .competency-block {
            margin-bottom: 0;
        }

        .competency-category-table {
            page-break-after: avoid;
        }

        .competency-block {
            margin-top: -1px;
            page-break-inside: avoid;
        }

        .competency-block .self-row td:not(.competency-name) {
            height: 0.24cm;
            padding-top: 1px;
            padding-bottom: 1px;
            font-size: 8.5pt;
            line-height: 1;
        }

        .center {
            text-align: center !important;
        }

        .competency-legend {
            margin: -3px 0 8px;
            padding: 4px;
            border: 1px solid #000;
            font-size: 8pt;
            line-height: 1.3;
        }

        .report-text {
            min-height: 3cm;
            white-space: pre-line;
        }

    </style>
</head>
<body>
    @php
        $logoPath = filled($berichtLogoPath ?? null)
            ? str_replace('\\', '/', $berichtLogoPath)
            : null;
        $selfAssessmentVisible = $berichtConfig['selbsteinschaetzung_anzeigen'] ?? true;
        $competencyColumnCount = 8;
    @endphp

    <table class="report-header">
        <tr>
            <td><h1>Abschlussbericht</h1></td>
            <td class="logo-cell">
                @if($logoPath)
                    <img src="file://{{ $logoPath }}" class="logo" alt="Berichtslogo">
                @endif
            </td>
        </tr>
    </table>

    <table class="report-table">
        <tr>
            <th colspan="2" class="section-title">1. Stammdaten</th>
        </tr>
        <tr>
            <td style="width: 50%;">Vorname: {{ $person->vorname }}</td>
            <td style="width: 50%;">Name: {{ $person->nachname }}</td>
        </tr>
        <tr>
            <td>Projekt: {{ $gruppe->projekt?->name ?? '-' }}</td>
            <td>Zeitraum: {{ $zeitraum ?: '-' }}</td>
        </tr>
        <tr>
            <td>Potenzialanalyse: {{ $profil?->name ?? ($berichtConfig['untertitel'] ?? '-') }}</td>
            <td>Erstellt: {{ $erstelltAm }}</td>
        </tr>
    </table>

    <table class="report-table competency-section">
        <tr>
            <th class="section-title">2. Kompetenzbereiche</th>
        </tr>
    </table>

    @foreach($merkmale as $bereich => $items)
        <table class="report-table competency-category-table">
            <tr>
                <th colspan="{{ $competencyColumnCount }}" class="category-title">{{ $bereich }}</th>
            </tr>
            <tr>
                <th width="33%" class="column-title competency-name">Kompetenz</th>
                <th width="5%" class="column-title assessment-source"></th>
                @foreach(range(1, 5) as $rating)
                    <th width="3.8%" class="column-title rating-heading">{{ $rating }}</th>
                @endforeach
                <th width="43%" class="column-title">Beurteilung</th>
            </tr>
        </table>

        @foreach($items as $item)
            <table class="report-table competency-block">
                <tbody class="competency-pair">
                    <tr class="coach-row">
                        <td width="33%" @if($selfAssessmentVisible) rowspan="2" @endif class="competency-name">{{ $item['label'] }}</td>
                        <td width="5%" class="assessment-source">TL</td>
                        @foreach(range(1, 5) as $rating)
                            <td width="3.8%" class="rating-cell">{{ (int) ($item['anleiter'] ?? 0) === $rating ? 'X' : '' }}</td>
                        @endforeach
                        <td width="43%" class="assessment-text">
                            {{ ($item['anleiter_beurteilung'] ?? null) ?: ($item['anleiter_bemerkung'] ?: '-') }}
                            @if(($item['anleiter_beurteilung'] ?? null)
                                && $item['anleiter_bemerkung']
                                && trim($item['anleiter_beurteilung']) !== trim($item['anleiter_bemerkung']))
                                <span class="assessment-note">Zusatz: {{ $item['anleiter_bemerkung'] }}</span>
                            @endif
                        </td>
                    </tr>
                    @if($selfAssessmentVisible)
                        <tr class="self-row">
                            <td width="5%" class="assessment-source">SE</td>
                            @foreach(range(1, 5) as $rating)
                                <td width="3.8%" class="rating-cell">{{ (int) ($item['selbst'] ?? 0) === $rating ? 'X' : '' }}</td>
                            @endforeach
                            <td width="43%" class="assessment-text"></td>
                        </tr>
                    @endif
                </tbody>
            </table>
        @endforeach
    @endforeach

    <div class="competency-legend">
        1 = im entwicklungsfähigen Maße vorhanden, 2 = im erkennbaren Maße vorhanden,
        3 = im deutlichen Maße vorhanden, 4 = im hohen Maße vorhanden,
        5 = im höchsten Maße vorhanden<br>
        TL = Beurteilung durch Testleitung/Auswerter/in{{ $selfAssessmentVisible ? ', SE = Selbsteinschätzung' : '' }}
    </div>

    @if(($berichtConfig['uebungsergebnisse_anzeigen'] ?? true) && $uebungen->isNotEmpty())
        <table class="report-table">
            <thead>
                <tr>
                    <th colspan="4" class="section-title">3. Übungsergebnisse</th>
                </tr>
                <tr>
                    <th class="column-title">Übung</th>
                    <th class="column-title center">Punkte</th>
                    <th class="column-title center">Fehler</th>
                    <th class="column-title center">Zeit</th>
                </tr>
            </thead>
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
        <table class="report-table">
            <tr>
                <th class="section-title">4. Stärken und Zusammenfassung</th>
            </tr>
            <tr>
                <td class="report-text">{{ $bericht?->bericht_text ?: 'Noch keine Zusammenfassung hinterlegt.' }}</td>
            </tr>
        </table>
    @endif

</body>
</html>
