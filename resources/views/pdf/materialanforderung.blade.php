<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Materialanforderung #{{ $anforderung->id }}</title>
    <style>
        @page { margin: 15mm 14mm 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font-family: DejaVu Sans, sans-serif; font-size: 9pt; }
        h1, h2, p { margin: 0; }
        .header { width: 100%; border-bottom: 2px solid #111827; padding-bottom: 7px; margin-bottom: 10px; }
        .header td { vertical-align: middle; }
        .logo { width: 105px; }
        .title { font-size: 18pt; font-weight: bold; }
        .subtitle { color: #6b7280; margin-top: 3px; }
        .section { margin-top: 10px; page-break-inside: avoid; }
        .section-title { background: #f97316; color: white; padding: 5px 7px; font-size: 10pt; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { border: 1px solid #9ca3af; padding: 5px 7px; width: 25%; vertical-align: top; }
        .label { display: block; color: #6b7280; font-size: 7.5pt; margin-bottom: 2px; }
        .items th { background: #f3f4f6; border: 1px solid #9ca3af; padding: 5px; font-size: 8pt; text-align: left; }
        .items td { border: 1px solid #9ca3af; padding: 5px; vertical-align: top; }
        .number { text-align: right; white-space: nowrap; }
        .totals { margin-left: auto; margin-top: 5px; width: 42%; }
        .totals td { border: 1px solid #9ca3af; padding: 4px 6px; }
        .totals .grand td { border-top: 2px solid #111827; font-size: 10pt; font-weight: bold; }
        .two-column td { width: 50%; vertical-align: top; border: 1px solid #9ca3af; padding: 6px; }
        .checks { margin-top: 3px; }
        .check { display: inline-block; width: 49%; margin: 2px 0; font-size: 8pt; }
        .box { display: inline-block; width: 10px; height: 10px; margin-right: 4px; border: 1px solid #111827; line-height: 8px; text-align: center; }
        .note { white-space: pre-wrap; line-height: 1.35; }
        .signatures { margin-top: 12px; }
        .signatures td { width: 33.33%; padding-right: 8px; vertical-align: top; }
        .signature-box { min-height: 50px; border: 1px solid #6b7280; padding: 6px; }
        .signature-name { margin-top: 12px; font-weight: bold; }
        .footer { position: fixed; bottom: -8mm; left: 0; right: 0; color: #6b7280; font-size: 7pt; text-align: center; }
        .status { display: inline-block; padding: 3px 7px; background: #f3f4f6; border: 1px solid #d1d5db; font-weight: bold; }
    </style>
</head>
<body>
@php
    $vergabe = $anforderung->vergabevermerk;
    $sachlich = $anforderung->genehmigungen->firstWhere('status', 'sachlich_genehmigt');
    $kaufmaennisch = $anforderung->genehmigungen->firstWhere('status', 'kaufmaennisch_genehmigt');
    $labels = [
        'nur_ein_anbieter' => 'Es existiert nur ein Anbieter',
        'besondere_gruende' => 'Aufgrund besonderer Gründe',
        'besondere_dringlichkeit' => 'Besondere Dringlichkeit',
        'zubehoer_ersatzteile' => 'Zubehör oder Ersatzteile zu vorhandenen Geräten',
        'vertragliche_gruende' => 'Aus vertraglichen Gründen',
        'guenstigster_anbieter' => 'Günstigster Anbieter',
    ];
@endphp

<table class="header">
    <tr>
        <td>
            <h1 class="title">Materialanforderung</h1>
            <p class="subtitle">Vorgang #{{ $anforderung->id }} · <span class="status">{{ str_replace('_', ' ', $anforderung->status) }}</span></p>
        </td>
        <td style="text-align:right"><img class="logo" src="{{ public_path('img/logo/zbb-logo-transparent.png') }}" alt="ZBB"></td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td><span class="label">Projekt</span><strong>{{ $anforderung->projekt?->name }}</strong></td>
        <td><span class="label">Kostenstelle</span><strong>{{ $anforderung->kostenstelle }}</strong></td>
        <td><span class="label">Antragsteller</span><strong>{{ $anforderung->besteller?->name }}</strong></td>
        <td><span class="label">Erstellt am</span><strong>{{ $anforderung->created_at?->format('d.m.Y') }}</strong></td>
    </tr>
    <tr>
        <td><span class="label">Benötigt am</span><strong>{{ $anforderung->benoetigt_am?->format('d.m.Y') ?: '–' }}</strong></td>
        <td><span class="label">Priorität</span><strong>{{ ucfirst($anforderung->prioritaet ?? 'normal') }}</strong></td>
        <td colspan="2"><span class="label">Bemerkungen</span><span class="note">{{ $anforderung->bemerkungen ?: '–' }}</span></td>
    </tr>
</table>

<div class="section">
    <h2 class="section-title">Artikel und Preise</h2>
    <table class="items">
        <thead><tr><th style="width:5%">Pos.</th><th style="width:35%">Artikel</th><th style="width:9%">Stück</th><th style="width:16%">Art.-Nummer</th><th style="width:13%;text-align:right">Einzelpreis</th><th style="width:8%;text-align:right">MwSt.</th><th style="width:14%;text-align:right">Gesamtpreis</th></tr></thead>
        <tbody>
        @foreach($anforderung->artikeln as $artikel)
            <tr>
                <td>{{ $artikel->pos }}</td>
                <td><strong>{{ $artikel->artikel }}</strong>@if($artikel->link)<br><span style="font-size:7pt;color:#6b7280">{{ $artikel->link }}</span>@endif</td>
                <td class="number">{{ number_format($artikel->stueck, 0, ',', '.') }}@if(in_array($anforderung->status, ['bestellt', 'teilweise_geliefert', 'geliefert'], true))<br><span style="font-size:7pt;color:#6b7280">geliefert: {{ number_format($artikel->gelieferte_menge, 0, ',', '.') }}</span>@endif</td>
                <td>{{ $artikel->art_nr ?: '–' }}</td>
                <td class="number">{{ number_format($artikel->einzelpreis, 2, ',', '.') }} €</td>
                <td class="number">{{ number_format($artikel->mwst, 2, ',', '.') }} %</td>
                <td class="number"><strong>{{ number_format($artikel->gesamtpreis, 2, ',', '.') }} €</strong></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <table class="totals">
        <tr><td>Summe netto</td><td class="number">{{ number_format($anforderung->gesamtpreis, 2, ',', '.') }} €</td></tr>
        <tr><td>Mehrwertsteuer</td><td class="number">{{ number_format($anforderung->endsumme - $anforderung->gesamtpreis, 2, ',', '.') }} €</td></tr>
        <tr class="grand"><td>Endsumme</td><td class="number">{{ number_format($anforderung->endsumme, 2, ',', '.') }} €</td></tr>
    </table>
</div>

<div class="section">
    <h2 class="section-title">Vergabevermerk</h2>
    <table class="two-column">
        <tr><td colspan="2"><span class="label">Kurzbeschreibung von Art und Umfang der Leistung</span><div class="note">{{ $vergabe?->kurzbeschreibung ?: '–' }}</div></td></tr>
        <tr>
            <td><span class="label">Art der Leistung</span><strong>{{ $vergabe?->lieferung_art ?: 'Lieferleistung' }}</strong></td>
            <td><span class="label">Lieferart</span><strong>{{ $vergabe?->lieferung_option ?: 'per Lieferung' }}</strong></td>
        </tr>
        <tr><td colspan="2"><span class="label">Marktbeschreibung / Begründung</span><div class="checks">@foreach($labels as $key => $label)<span class="check"><span class="box">{{ in_array($key, $vergabe?->begruendung_optionen ?? [], true) ? 'X' : '' }}</span>{{ $label }}</span>@endforeach</div>@if($vergabe?->begruendung)<div class="note" style="margin-top:5px">{{ $vergabe->begruendung }}</div>@endif</td></tr>
        <tr><td><span class="label">Lieferant</span><div class="note">{{ $vergabe?->lieferant ?: '–' }}</div></td><td><span class="label">Lieferadresse</span><div class="note">{{ $vergabe?->lieferadresse ?: '–' }}</div></td></tr>
        <tr><td><span class="label">Bestellnummer</span><strong>{{ $vergabe?->bestellnummer ?: '–' }}</strong></td><td><span class="label">Aktueller Status</span><strong>{{ str_replace('_', ' ', $anforderung->status) }}</strong></td></tr>
    </table>
</div>

<table class="signatures">
    <tr>
        <td><div class="signature-box"><span class="label">Antragsteller</span><div class="signature-name">{{ $anforderung->besteller?->name }}</div><div>{{ $anforderung->created_at?->format('d.m.Y H:i') }}</div></div></td>
        <td><div class="signature-box"><span class="label">Sachliche Genehmigung</span><div class="signature-name">{{ $sachlich?->genehmiger?->name ?: 'Noch offen' }}</div><div>{{ $sachlich?->created_at?->format('d.m.Y H:i') }}</div></div></td>
        <td><div class="signature-box"><span class="label">Kaufmännische Genehmigung</span><div class="signature-name">{{ $kaufmaennisch?->genehmiger?->name ?: 'Noch offen' }}</div><div>{{ $kaufmaennisch?->created_at?->format('d.m.Y H:i') }}</div></div></td>
    </tr>
</table>

<div class="footer">Elektronisch erzeugt am {{ now()->format('d.m.Y H:i') }} · Materialanforderung #{{ $anforderung->id }}</div>
</body>
</html>
