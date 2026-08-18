<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Auswertungsbogen PA neu Roland {{ $schulname }} {{ $schuljahr }} Teil {{ $teil }}</title>
    <style>
        @page { margin: 7mm 11mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: #111;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 7.7pt;
        }

        .sheet {
            position: relative;
            height: 196mm;
            overflow: hidden;
            page-break-after: always;
        }

        .sheet:last-child { page-break-after: auto; }

        h1 {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 8mm;
            margin: 0;
            text-align: center;
            font-size: 12pt;
            font-weight: 400;
            line-height: 6mm;
        }

        table { border-collapse: collapse; table-layout: fixed; }

        .meta {
            position: absolute;
            top: 8mm;
            left: 0;
            width: 100%;
            height: 14mm;
            border: 2px solid #111;
        }

        .meta td {
            height: 6.7mm;
            border: 1px solid #111;
            padding: 0.7mm 1mm;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
        }

        .meta .name { width: 46%; }
        .meta .birth { width: 24%; }
        .meta .gender { width: 30%; }
        .meta .school { width: 46%; }
        .meta .class { width: 16%; }
        .meta .days { width: 38%; }
        .meta strong { font-weight: 400; }

        .main {
            position: absolute;
            top: 23mm;
            left: 0;
            width: 100%;
            height: 151mm;
        }

        .left-pane {
            position: absolute;
            top: 0;
            left: 0;
            width: 34.5%;
            height: 100%;
            padding-right: 0;
            border-right: 3px solid #111;
        }

        .right-pane {
            position: absolute;
            top: 0;
            left: 34.5%;
            width: 65.5%;
            height: 100%;
            padding-left: 0;
        }

        .task-table,
        .competence-table,
        .scale-table { width: 100%; }

        .task-table thead th {
            height: 7.5mm;
            border-bottom: 3px solid #aaa;
            font-weight: 400;
            text-align: center;
            vertical-align: bottom;
            padding-bottom: 1mm;
        }

        .task-table .number { width: 11%; }
        .task-table .task-name { width: 51%; text-align: left; }
        .task-table .pmax { width: 8%; }
        .task-table .points { width: 11%; }
        .task-table .time { width: 19%; }

        .task-table tbody td {
            height: 5.3mm;
            border: 1px solid #111;
            border-bottom: 3px solid #aaa;
            padding: 0.05mm 0.5mm;
            line-height: 4.4mm;
            vertical-align: middle;
        }

        .task-table tbody td:nth-child(1),
        .task-table tbody td:nth-child(3),
        .task-table tbody td:nth-child(4) { text-align: center; }

        .pmax-label {
            display: inline-block;
            font-size: 8pt;
            transform: rotate(-90deg);
        }

        .black-box {
            display: inline-block;
            width: 5mm;
            height: 3.8mm;
            padding: 0.65mm;
            background: #050505;
            vertical-align: middle;
        }

        .black-box > span {
            display: block;
            width: 100%;
            height: 100%;
            background: #fff;
        }

        .scale-wrap {
            height: 29mm;
            padding: 1.2mm 0 0 11%;
        }

        .scale-table {
            width: 54%;
            font-size: 6.1pt;
            line-height: 1;
        }

        .scale-table th,
        .scale-table td {
            height: 3.1mm;
            border: 0.7px solid #333;
            padding: 0.15mm 0.35mm;
            text-align: center;
        }

        .scale-table th:first-child,
        .scale-table td:first-child { width: 43%; text-align: left; }
        .scale-table th:nth-child(2),
        .scale-table td:nth-child(2) { width: 15%; }

        .block { width: 100%; }

        .block-title {
            height: 8mm;
            border-bottom: 3px solid #aaa;
            text-align: center;
            vertical-align: bottom;
            font-size: 8.2pt;
            font-weight: 400;
        }

        .block-title .title-text { width: 65%; padding-bottom: 1mm; }
        .block-title .rating-head { width: 35%; padding-bottom: 0; }

        .rating-grid { width: 100%; }
        .block-title .rating-grid { height: 7.5mm; }
        .competence-table .rating-grid { height: 5.3mm; }

        .rating-grid td {
            border: 1px solid #111;
            text-align: center;
            vertical-align: middle;
        }

        .rating-grid td.empty {
            border-color: #aaa;
            background: #aaa;
        }

        .rating-grid td.optional { background: #bbb; }

        .competence-table td {
            height: 5.3mm;
            border-bottom: 3px solid #aaa;
            padding: 0.05mm 0.5mm;
            line-height: 4.3mm;
            vertical-align: middle;
        }

        .competence-table .comp-number { width: 5%; }
        .competence-table .comp-task { width: 28%; }
        .competence-table .comp-name { width: 32%; }
        .competence-table .comp-rating { width: 35%; padding: 0; }

        .block-gap { height: 2.5mm; background: #fff; }

        .legend {
            position: absolute;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 18mm;
            font-size: 6.8pt;
            line-height: 1.35;
        }

        .legend td { vertical-align: bottom; }
        .block-title th { font-weight: 400; }
        .legend .left { width: 38%; }
        .legend .middle { width: 34%; text-align: center; }
        .legend .right { width: 28%; text-align: right; }
    </style>
</head>
<body>
@php
    $tasks = [
        [1, 'Auto schneiden', 17, false],
        [2, 'Linien ziehen', 34, false],
        [3, 'Figuren ergänzen', 22, false],
        [4, 'Robina Hood', null, true],
        [5, 'Fahrradtour', null, true],
        [6, 'Spaghetti-Gericht', null, true],
        [7, 'Stern ausmalen', 22, false],
        [8, 'Pfeile verschieben', 15, false],
        [9, 'Zimmer messen', 11, false],
        [10, 'Fisch raspeln', 8, false],
        [11, 'Drahttreppe biegen', 24, false],
        [12, 'Sturmfreie Bude', null, true],
        [13, 'Hammerwerk', null, true],
        [14, 'Turmbau', null, true],
        [15, '1.000-€-Gewinn', null, true],
    ];

    $scaleRows = [
        ['Auto', 'bis', 3, 7, 10, 14, 17],
        ['Linien', 'bis', 7, 14, 20, 27, 34],
        ['Figuren', 'bis', 4, 9, 13, 18, 22],
        ['Stern', 'bis', 4, 9, 13, 18, 22],
        ['Zimmer', 'bis', 2, 5, 7, 9, 11],
        ['Pfeile', 'bis', 3, 6, 9, 12, 15],
        ['Fisch', 'bis', 2, 3, 5, 6, 8],
        ['Treppe', 'bis', 5, 10, 14, 19, 24],
    ];

    $blocks = [
        [
            'title' => 'BK - Berufsübergreifende Kompetenzen',
            'headers' => [1, 2, 3, 7, 8, 9, 10, 11],
            'rows' => [
                [1, 'Auto schneiden', 'a) Feinmotorik', ['w', 'w', 'w', 'o', 'o', 'o', '', '']],
                [2, 'Linien ziehen', 'b) Grobmotorik', ['w', 'w', 'w', '', '', '', 'o', 'o']],
                [3, 'Figuren ergänzen', 'c) Wahrnehmung + Symmetrie', ['w', 'w', 'w', '', '', 'o', '', '']],
            ],
        ],
        [
            'title' => 'MK - Methodenkompetenz',
            'headers' => [4, 5, 6, 13, 14],
            'rows' => [
                [4, 'Robina Hood', 'a) Analyse- / Problemlösefähigkeit', ['w', 'w', 'w', 'o', 'o']],
                [5, 'Fahrradtour', 'b) Arbeitsplanung', ['w', 'w', 'w', 'o', 'o']],
                [6, 'Spaghetti-Gericht', '', []],
            ],
        ],
        [
            'title' => 'PK - Personale Kompetenzen',
            'headers' => [7, 8, 9, 10, 11, 1, 2, 3],
            'rows' => [
                [7, 'Stern ausmalen', 'a) Motivation / Leistungsb.', ['w', 'w', 'w', 'w', 'w', 'o', 'o', 'o']],
                [8, 'Pfeile verschieben', 'b) Durchhaltevermögen', ['w', 'w', 'w', 'w', 'w', 'o', 'o', 'o']],
                [9, 'Zimmer messen', 'c) Sorgfalt u. Genauigkeit', ['w', 'w', 'w', 'w', 'w', 'o', 'o', 'o']],
                [10, 'Fisch raspeln', '', []],
                [11, 'Drahttreppe biegen', '', []],
            ],
        ],
        [
            'title' => 'SK - Soziale Kompetenzen',
            'headers' => [12, 13, 14, 15, 4, 5, 6],
            'rows' => [
                [12, 'Sturmfreie Bude', 'a) Kommunikationsfähigkeit', ['w', 'w', 'w', 'w', 'o', 'o', 'o']],
                [13, 'Hammerwerk', 'b) Teamfähigkeit', ['w', 'w', 'w', 'w', 'o', 'o', 'o']],
                [14, 'Turmbau', 'c) Umgangsformen', ['w', 'w', 'w', 'w', 'o', 'o', 'o']],
                [15, '1.000-€-Gewinn', '', []],
            ],
        ],
    ];
@endphp

@foreach ($teilnehmer as $item)
    <section class="sheet">
        <h1>Auswertung hametBOP</h1>

        <table class="meta">
            <tr>
                <td class="name"><strong>Name:</strong>&nbsp; {{ $item['name'] }}</td>
                <td class="birth"><strong>geb.:</strong>&nbsp; {{ $item['geburtsdatum'] }}</td>
                <td class="gender"><strong>Geschlecht:</strong>&nbsp; {{ $item['geschlecht'] }}</td>
            </tr>
            <tr>
                <td class="school"><strong>Schule:</strong>&nbsp; {{ $item['schule'] }}</td>
                <td class="class"><strong>Klasse:</strong>&nbsp; {{ $item['klasse'] }}</td>
                <td class="days"><strong>Tage:</strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; und</td>
            </tr>
        </table>

        <div class="main">
                <div class="left-pane">
                    <table class="task-table">
                        <thead>
                            <tr>
                                <th class="number"></th>
                                <th class="task-name">Aufgaben</th>
                                <th class="pmax"><span class="pmax-label">Pmax</span></th>
                                <th class="points">P</th>
                                <th class="time">Zeit</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach ($tasks as [$number, $name, $pmax, $hasBox])
                            <tr>
                                <td>{{ $number }}</td>
                                <td>{{ $name }}</td>
                                <td>{{ $pmax }}</td>
                                <td>@if ($hasBox)<span class="black-box"><span></span></span>@endif</td>
                                <td></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>

                    <div class="scale-wrap">
                        <table class="scale-table">
                            <thead><tr><th></th><th></th><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th></tr></thead>
                            <tbody>
                            @foreach ($scaleRows as $scaleRow)
                                <tr>
                                @foreach ($scaleRow as $value)<td>{{ $value }}</td>@endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="right-pane">
                    @foreach ($blocks as $blockIndex => $block)
                        @if ($blockIndex > 0)<div class="block-gap"></div>@endif
                        <table class="block">
                            <tr class="block-title">
                                <th class="title-text">{{ $block['title'] }}</th>
                                <th class="rating-head">
                                    <table class="rating-grid"><tr>
                                    @foreach ($block['headers'] as $header)<td>{{ $header }}</td>@endforeach
                                    </tr></table>
                                </th>
                            </tr>
                        </table>
                        <table class="competence-table">
                            @foreach ($block['rows'] as [$number, $taskName, $competence, $states])
                                <tr>
                                    <td class="comp-number">{{ $number }}</td>
                                    <td class="comp-task">{{ $taskName }}</td>
                                    <td class="comp-name">{{ $competence }}</td>
                                    <td class="comp-rating">
                                        @if (count($states))
                                            <table class="rating-grid"><tr>
                                            @foreach ($states as $state)
                                                <td class="{{ $state === 'o' ? 'optional' : ($state === '' ? 'empty' : '') }}"></td>
                                            @endforeach
                                            </tr></table>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    @endforeach
                </div>
        </div>

        <table class="legend">
            <tr>
                <td class="left">1 = im entwicklungsfähigen Maße vorhanden<br>2 = im erkennbaren Maße vorhanden</td>
                <td class="middle">3 = im deutlichen Maße vorhanden<br>4 = im hohen Maße vorhanden</td>
                <td class="right">5 = im höchsten Maße vorhanden<br>grau hinterlegte Felder: optional zu beurteilen</td>
            </tr>
        </table>
    </section>
@endforeach
</body>
</html>
