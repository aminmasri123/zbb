<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 46mm 15mm 38mm; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: #111; }
        table { border-collapse: collapse; }
        .attendance { width: {{ $paper === 'A3' ? 324 : 234 }}mm; table-layout: auto; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .document-header { position: fixed; top: -34mm; left: 0; right: 0; text-align: left; }
        h1 { margin: 0 0 4mm; font-size: 14pt; line-height: 1.25; font-weight: bold; }
        .metadata { width: 100%; font-size: 9pt; line-height: 1.4; }
        .metadata td { padding: 0 0 1mm; vertical-align: top; }
        .metadata .label { width: 24mm; font-weight: bold; }
        .metadata .date { width: 61mm; text-align: right; }
        .date-label { padding-right: 3mm; font-weight: bold; }
        .columns th { padding: 2mm 2.5mm; border: 0.5pt solid #555; background: #f0f2f4; font-size: 9pt; text-align: left; line-height: 1.2; }
        .attendance > tbody > tr > td { height: {{ $paper === 'A3' ? 6 : 6.5 }}mm; padding: 0 2.5mm; border: 0.5pt solid #555; vertical-align: middle; line-height: 1.25; overflow-wrap: anywhere; }
        .attendance .center { text-align: center; }
        .signature { text-align: center; }
        .signature img { max-width: 53mm; max-height: 5.5mm; vertical-align: middle; }
        .sub-label { font-size: 8pt; font-weight: normal; }
    </style>
</head>
<body>
<div class="document-header">
    <h1>{{ $title }}</h1>
    <table class="metadata">
        <tr><td class="label">Schule:</td><td>{{ $school }}</td><td class="date"><span class="date-label">Termin:</span>{{ $date }}</td></tr>
        <tr><td class="label">Schulform:</td><td colspan="2">{{ $schoolForm }}</td></tr>
        <tr><td class="label">Klasse/n:</td><td colspan="2">{{ $classes }}</td></tr>
    </table>
</div>
<table class="attendance">
    <thead>
        <tr class="columns">
            <th class="center" style="width: 6mm">Nr.</th>
            <th style="width: {{ $paper === 'A3' ? 105 : 60 }}mm">Name</th>
            <th style="width: {{ $paper === 'A3' ? 105 : 60 }}mm">Vorname</th>
            <th class="center" style="width: 28mm">Geschlecht<br><span class="sub-label">w/m</span></th>
            <th style="width: 55mm">Unterschrift<br><span class="sub-label">Schüler/-in</span></th>
        </tr>
    </thead>
    <tbody>
    @foreach ($rows as $row)
        <tr>
            <td class="center">{{ $row['number'] }}</td>
            <td>{{ $row['lastName'] }}</td>
            <td>{{ $row['firstName'] }}</td>
            <td class="center">{{ $row['gender'] }}</td>
            <td class="signature">@if ($row['signature'])<img src="{{ $row['signature'] }}" alt="">@endif</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
