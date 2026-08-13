<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Materialanforderung #{{ $anforderung->id }}</title>
    <style>
        @page { margin: 10mm 13mm 13mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #182235;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            line-height: 1.35;
        }
        h1, h2, p { margin: 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .document-header { margin-bottom: 5mm; }
        .document-header td { vertical-align: middle; }
        .brand-bar { width: 5px; height: 48px; background: #f47a1f; }
        .header-copy { padding-left: 12px; }
        .eyebrow { color: #f47a1f; font-size: 7.5pt; font-weight: bold; letter-spacing: 1.2px; text-transform: uppercase; }
        .title { margin-top: 1px; color: #13233f; font-size: 19pt; line-height: 1.05; }
        .document-number { margin-top: 5px; color: #687386; font-size: 8.5pt; }
        .logo { width: 92px; }
        .status-pill {
            display: inline-block;
            margin-left: 6px;
            padding: 3px 8px;
            border-radius: 10px;
            background: #eef2f7;
            color: #334155;
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: capitalize;
        }
        .header-rule { margin-top: 7px; border-bottom: 2px solid #13233f; }

        .facts { margin-bottom: 3mm; }
        .facts td {
            width: 25%;
            padding: 6px 8px;
            border: 1px solid #d9dee7;
            vertical-align: top;
        }
        .facts tr:first-child td { background: #f8fafc; }
        .label {
            display: block;
            margin-bottom: 3px;
            color: #6b778c;
            font-size: 7pt;
            font-weight: normal;
            text-transform: uppercase;
            letter-spacing: .35px;
        }
        .value { color: #13233f; font-size: 9pt; font-weight: bold; }
        .priority-high { color: #b42318; }
        .note-box {
            margin-top: 5px;
            padding: 5px 8px;
            border-left: 3px solid #f47a1f;
            background: #fff8f1;
            color: #374151;
        }

        .section { margin-top: 4mm; }
        .section-heading {
            padding: 5px 8px;
            border-left: 4px solid #f47a1f;
            background: #13233f;
            color: white;
            font-size: 10pt;
            font-weight: bold;
        }
        .section-caption { float: right; color: #cbd5e1; font-size: 7pt; font-weight: normal; }

        .items { font-size: 8pt; }
        .items th {
            padding: 5px;
            border-bottom: 1px solid #cfd6e1;
            background: #edf1f6;
            color: #475569;
            font-size: 7pt;
            text-align: left;
            text-transform: uppercase;
        }
        .items td {
            padding: 5px;
            border-bottom: 1px solid #d9dee7;
            vertical-align: top;
        }
        .items tbody tr:nth-child(even) td { background: #fafbfc; }
        .position { color: #f47a1f; font-weight: bold; text-align: center; }
        .article-name { color: #13233f; font-size: 8.5pt; font-weight: bold; }
        .product-link { margin-top: 3px; color: #2563a6; font-size: 7pt; text-decoration: none; }
        .article-number { white-space: pre-line; font-size: 7.5pt; }
        .number { text-align: right; white-space: nowrap; }
        .delivered { display: block; margin-top: 2px; color: #64748b; font-size: 6.5pt; }

        .summary-wrap { margin-top: 2mm; }
        .summary-spacer { width: 55%; }
        .summary { width: 45%; }
        .summary td { padding: 4px 8px; border-bottom: 1px solid #d9dee7; }
        .summary .amount { text-align: right; white-space: nowrap; }
        .summary .grand td {
            padding-top: 5px;
            padding-bottom: 5px;
            border-top: 2px solid #13233f;
            border-bottom: 0;
            background: #fff4e8;
            color: #13233f;
            font-size: 10pt;
            font-weight: bold;
        }

        .details td {
            width: 50%;
            padding: 5px 8px;
            border: 1px solid #d9dee7;
            vertical-align: top;
        }
        .details .wide { width: 100%; }
        .text { white-space: pre-wrap; }
        .check-grid { margin-top: 3px; }
        .check-grid td { width: 50%; padding: 1px 5px 1px 0; border: 0; font-size: 7.3pt; }
        .box {
            display: inline-block;
            width: 10px;
            height: 10px;
            margin-right: 5px;
            border: 1px solid #64748b;
            color: #f47a1f;
            font-size: 7pt;
            font-weight: bold;
            line-height: 8px;
            text-align: center;
        }

        .approvals { margin-top: 3mm; }
        .approvals td { width: 33.33%; padding-right: 7px; vertical-align: top; }
        .approvals td:last-child { padding-right: 0; }
        .approval-card {
            min-height: 48px;
            padding: 5px 7px;
            border: 1px solid #d0d7e2;
            border-top: 3px solid #13233f;
            background: #f8fafc;
        }
        .approval-open { border-top-color: #cbd5e1; }
        .approval-name { margin-top: 5px; color: #13233f; font-weight: bold; }
        .approval-date { margin-top: 2px; color: #6b778c; font-size: 7pt; }

        .footer {
            position: fixed;
            right: 0;
            bottom: -9mm;
            left: 0;
            padding-top: 4px;
            border-top: 1px solid #d9dee7;
            color: #7b8798;
            font-size: 6.8pt;
            text-align: center;
        }
    </style>
</head>
<body>
@php
    $vergabe = $anforderung->vergabevermerk;
    $sachlich = $anforderung->genehmigungen->firstWhere('status', 'sachlich_genehmigt');
    $kaufmaennisch = $anforderung->genehmigungen->firstWhere('status', 'kaufmaennisch_genehmigt');
    $status = str_replace('_', ' ', $anforderung->status);
    $labels = [
        'nur_ein_anbieter' => 'Es existiert nur ein Anbieter',
        'besondere_gruende' => 'Aufgrund besonderer Gründe',
        'besondere_dringlichkeit' => 'Besondere Dringlichkeit',
        'zubehoer_ersatzteile' => 'Zubehör oder Ersatzteile zu vorhandenen Geräten',
        'vertragliche_gruende' => 'Aus vertraglichen Gründen',
        'guenstigster_anbieter' => 'Günstigster Anbieter',
    ];
@endphp

<table class="document-header">
    <tr>
        <td style="width:5px"><div class="brand-bar"></div></td>
        <td class="header-copy">
            <div class="eyebrow">Beschaffung</div>
            <h1 class="title">Materialanforderung</h1>
            <p class="document-number">Vorgang #{{ $anforderung->id }} <span class="status-pill">{{ $status }}</span></p>
        </td>
        <td style="width:110px;text-align:right"><img class="logo" src="{{ public_path('img/logo/zbb-logo-transparent.png') }}" alt="ZBB"></td>
    </tr>
    <tr><td colspan="3"><div class="header-rule"></div></td></tr>
</table>

<table class="facts">
    <tr>
        <td><span class="label">Projekt</span><span class="value">{{ $anforderung->projekt?->name }}</span></td>
        <td><span class="label">Kostenstelle</span><span class="value">{{ $anforderung->kostenstelle }}</span></td>
        <td><span class="label">Antragsteller</span><span class="value">{{ $anforderung->besteller?->name }}</span></td>
        <td><span class="label">Erstellt am</span><span class="value">{{ $anforderung->created_at?->format('d.m.Y') }}</span></td>
    </tr>
    <tr>
        <td><span class="label">Benötigt am</span><span class="value">{{ $anforderung->benoetigt_am?->format('d.m.Y') ?: '-' }}</span></td>
        <td><span class="label">Priorität</span><span class="value {{ $anforderung->prioritaet === 'dringend' ? 'priority-high' : '' }}">{{ ucfirst($anforderung->prioritaet ?? 'normal') }}</span></td>
        <td colspan="2"><span class="label">Bestellnummer</span><span class="value">{{ $vergabe?->bestellnummer ?: 'Noch nicht vergeben' }}</span></td>
    </tr>
</table>

@if($anforderung->bemerkungen)
    <div class="note-box"><span class="label">Bemerkungen</span><span class="text">{{ $anforderung->bemerkungen }}</span></div>
@endif

<div class="section">
    <h2 class="section-heading">Artikel und Preise <span class="section-caption">{{ $anforderung->artikeln->count() }} Position(en)</span></h2>
    <table class="items">
        <thead>
            <tr>
                <th style="width:5%;text-align:center">Pos.</th>
                <th style="width:36%">Artikel</th>
                <th style="width:7%;text-align:right">Stück</th>
                <th style="width:15%">Art.-Nr.</th>
                <th style="width:13%;text-align:right">Einzelpreis</th>
                <th style="width:9%;text-align:right">MwSt.</th>
                <th style="width:15%;text-align:right">Gesamt</th>
            </tr>
        </thead>
        <tbody>
        @foreach($anforderung->artikeln as $artikel)
            <tr>
                <td class="position">{{ $artikel->pos }}</td>
                <td>
                    <div class="article-name">{{ $artikel->artikel }}</div>
                    @if($artikel->link)
                        <a class="product-link" href="{{ $artikel->link }}">Produktlink öffnen</a>
                    @endif
                </td>
                <td class="number">
                    {{ number_format($artikel->stueck, 0, ',', '.') }}
                    @if(in_array($anforderung->status, ['bestellt', 'teilweise_geliefert', 'geliefert'], true))
                        <span class="delivered">Geliefert: {{ number_format($artikel->gelieferte_menge, 0, ',', '.') }}</span>
                    @endif
                </td>
                <td class="article-number">{{ wordwrap((string) ($artikel->art_nr ?: '-'), 15, "\n", true) }}</td>
                <td class="number">{{ number_format($artikel->einzelpreis, 2, ',', '.') }} €</td>
                <td class="number">{{ number_format($artikel->mwst, 0, ',', '.') }} %</td>
                <td class="number"><strong>{{ number_format($artikel->gesamtpreis, 2, ',', '.') }} €</strong></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="summary-wrap">
        <tr>
            <td class="summary-spacer"></td>
            <td class="summary">
                <table>
                    <tr><td>Summe netto</td><td class="amount">{{ number_format($anforderung->gesamtpreis, 2, ',', '.') }} €</td></tr>
                    <tr><td>Mehrwertsteuer</td><td class="amount">{{ number_format($anforderung->endsumme - $anforderung->gesamtpreis, 2, ',', '.') }} €</td></tr>
                    <tr class="grand"><td>Endsumme</td><td class="amount">{{ number_format($anforderung->endsumme, 2, ',', '.') }} €</td></tr>
                </table>
            </td>
        </tr>
    </table>
</div>

<div class="section">
    <h2 class="section-heading">Vergabevermerk</h2>
    <table class="details">
        <tr>
            <td colspan="2" class="wide"><span class="label">Kurzbeschreibung von Art und Umfang der Leistung</span><div class="text">{{ $vergabe?->kurzbeschreibung ?: '-' }}</div></td>
        </tr>
        <tr>
            <td><span class="label">Art der Leistung</span><span class="value">{{ $vergabe?->lieferung_art ?: 'Lieferleistung' }}</span></td>
            <td><span class="label">Lieferart</span><span class="value">{{ $vergabe?->lieferung_option ?: 'per Lieferung' }}</span></td>
        </tr>
        <tr>
            <td colspan="2" class="wide">
                <span class="label">Marktbeschreibung / Begründung</span>
                <table class="check-grid">
                    @foreach(array_chunk($labels, 2, true) as $row)
                        <tr>
                            @foreach($row as $key => $label)
                                <td><span class="box">{{ in_array($key, $vergabe?->begruendung_optionen ?? [], true) ? 'X' : '' }}</span>{{ $label }}</td>
                            @endforeach
                            @if(count($row) === 1)<td></td>@endif
                        </tr>
                    @endforeach
                </table>
                @if($vergabe?->begruendung)<div class="text" style="margin-top:6px">{{ $vergabe->begruendung }}</div>@endif
            </td>
        </tr>
        <tr>
            <td><span class="label">Lieferant</span><div class="text">{{ $vergabe?->lieferant ?: '-' }}</div></td>
            <td><span class="label">Lieferadresse</span><div class="text">{{ $vergabe?->lieferadresse ?: '-' }}</div></td>
        </tr>
    </table>
</div>

<table class="approvals">
    <tr>
        <td>
            <div class="approval-card">
                <span class="label">Antragsteller</span>
                <div class="approval-name">{{ $anforderung->besteller?->name }}</div>
                <div class="approval-date">Erstellt am {{ $anforderung->created_at?->format('d.m.Y H:i') }}</div>
            </div>
        </td>
        <td>
            <div class="approval-card {{ $sachlich ? '' : 'approval-open' }}">
                <span class="label">Sachliche Genehmigung</span>
                <div class="approval-name">{{ $sachlich?->genehmiger?->name ?: 'Noch offen' }}</div>
                @if($sachlich)<div class="approval-date">Genehmigt am {{ $sachlich->created_at?->format('d.m.Y H:i') }}</div>@endif
            </div>
        </td>
        <td>
            <div class="approval-card {{ $kaufmaennisch ? '' : 'approval-open' }}">
                <span class="label">Kaufmännische Genehmigung</span>
                <div class="approval-name">{{ $kaufmaennisch?->genehmiger?->name ?: 'Noch offen' }}</div>
                @if($kaufmaennisch)<div class="approval-date">Genehmigt am {{ $kaufmaennisch->created_at?->format('d.m.Y H:i') }}</div>@endif
            </div>
        </td>
    </tr>
</table>

<div class="footer">Elektronisch erzeugt am {{ now()->format('d.m.Y H:i') }} | Materialanforderung #{{ $anforderung->id }}</div>
</body>
</html>
