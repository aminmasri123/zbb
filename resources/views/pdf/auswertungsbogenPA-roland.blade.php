<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Auswertungsbogen PA neu Roland {{ $schulname }} {{ $schuljahr }} Teil {{ $teil }}</title>
    <style>
        @page {
            margin: 8mm 10mm 9mm 10mm;
        }

        body {
            margin: 0;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8.7pt;
            color: #111;
        }

        .sheet {
            position: relative;
            min-height: 187mm;
            page-break-after: always;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        h1 {
            margin: 0 0 4mm;
            text-align: center;
            font-size: 14.5pt;
            font-weight: 700;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 2mm;
        }

        .meta td {
            border: 1px solid #111;
            height: 6.4mm;
            padding: 1mm 1.5mm;
            vertical-align: middle;
        }

        .meta .label {
            width: 18mm;
            font-size: 8pt;
        }

        .meta .value {
            font-weight: 600;
        }

        .divider {
            border-top: 3px solid #333;
            margin-bottom: 2mm;
        }

        .content {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .left,
        .middle,
        .right {
            display: table-cell;
            vertical-align: top;
        }

        .left {
            width: 37%;
            padding-right: 4mm;
        }

        .middle {
            width: 43%;
            padding-right: 4mm;
        }

        .right {
            width: 20%;
        }

        .section-title {
            margin: 1.7mm 0 1mm;
            text-align: center;
            font-weight: 700;
        }

        .section-title .code {
            color: #d11;
        }

        .row {
            display: table;
            width: 100%;
            min-height: 7.2mm;
            margin-bottom: 0.6mm;
        }

        .task,
        .competence,
        .rating,
        .observation-label,
        .observation {
            display: table-cell;
            vertical-align: middle;
        }

        .task {
            width: 50%;
        }

        .competence {
            width: 50%;
            padding-left: 2mm;
        }

        .rating {
            width: 39mm;
            text-align: right;
            white-space: nowrap;
        }

        .box {
            display: inline-block;
            width: 6.1mm;
            height: 6.1mm;
            border: 1.4px solid #111;
            margin-left: 1mm;
        }

        .task .box {
            margin-left: 0;
            margin-right: 1.2mm;
        }

        .observation-title {
            margin: 0 0 2mm;
            text-align: center;
            font-weight: 700;
        }

        .faces,
        .observation {
            text-align: center;
        }

        .face {
            display: inline-block;
            width: 9mm;
            height: 9mm;
            line-height: 9mm;
            border: 1.3px solid #111;
            font-size: 11pt;
        }

        .observation-row {
            margin: 5.2mm 0 7mm;
        }

        .legend {
            position: absolute;
            left: 0;
            bottom: 8mm;
            width: 78%;
            font-size: 7.5pt;
            line-height: 1.35;
            color: #333;
        }

        .footer-number {
            position: absolute;
            right: 0;
            bottom: 0;
            width: 42mm;
            border-top: 1px solid #333;
            padding-top: 1.2mm;
            text-align: right;
            font-size: 12pt;
            font-weight: 700;
        }
    </style>
</head>
<body>
@foreach ($teilnehmer as $item)
    <section class="sheet">
        <h1>Auswertung hametBOP</h1>

        <table class="meta">
            <tr>
                <td class="label">Name:</td>
                <td class="value" colspan="5">{{ $item['name'] }}</td>
                <td class="label">geb.:</td>
                <td class="value" colspan="3">{{ $item['geburtsdatum'] }}</td>
                <td class="label">Geschlecht:</td>
                <td class="value" colspan="3">{{ $item['geschlecht'] }}</td>
            </tr>
            <tr>
                <td class="label">Schule:</td>
                <td class="value" colspan="5">{{ $item['schule'] }}</td>
                <td class="label">Klasse:</td>
                <td class="value" colspan="2">{{ $item['klasse'] }}</td>
                <td class="label">Tage:</td>
                <td class="value" colspan="2">&nbsp;</td>
                <td class="label">und</td>
                <td class="value">&nbsp;</td>
            </tr>
        </table>

        <div class="divider"></div>

        <div class="content">
            <div class="left">
                <div class="section-title"><span class="code">BK</span> - Berufsuebergreifende Kompetenzen</div>
                <div class="row"><span class="task"><span class="box"></span>Auto schneiden</span><span class="competence">a) Feinmotorik</span></div>
                <div class="row"><span class="task"><span class="box"></span>Linien ziehen</span><span class="competence">b) Grobmotorik</span></div>
                <div class="row"><span class="task"><span class="box"></span>Figuren ergaenzen</span><span class="competence">c) Wahrnehmung und Symmetrie</span></div>

                <div class="section-title"><span class="code">MK</span> - Methodenkompetenz</div>
                <div class="row"><span class="task"><span class="box"></span>Robin Hood</span><span class="competence">a) Analyse-/Problemloesefaehigkeit</span></div>
                <div class="row"><span class="task"><span class="box"></span>Fahrradtour</span><span class="competence">b) Arbeitsplanung</span></div>

                <div class="section-title"><span class="code">PK</span> - Personale Kompetenzen</div>
                <div class="row"><span class="task"><span class="box"></span>Stern ausmalen</span><span class="competence">a) Motivation/Leistungsbereitschaft</span></div>
                <div class="row"><span class="task"><span class="box"></span>Zimmer messen</span><span class="competence">b) Durchhaltevermoegen</span></div>
                <div class="row"><span class="task"><span class="box"></span>Pfeile verschieben</span><span class="competence">c) Sorgfalt/Genauigkeit</span></div>

                <div class="section-title"><span class="code">SK</span> - Soziale Kompetenzen</div>
                <div class="row"><span class="task"><span class="box"></span>Sturmfreie Bude</span><span class="competence">a) Kommunikationsfaehigkeit</span></div>
                <div class="row"><span class="task"><span class="box"></span>Hammerwerk</span><span class="competence">b) Teamfaehigkeit</span></div>
                <div class="row"><span class="task"><span class="box"></span>Turnbau</span><span class="competence">c) Umgangsformen</span></div>
                <div class="row"><span class="task"><span class="box"></span>1.000 EUR Gewinn</span><span class="competence"></span></div>
            </div>

            <div class="middle">
                <div class="section-title">Kompetenzen (Tendenzen 1 - 5)</div>
                @for ($i = 0; $i < 13; $i++)
                    <div class="row">
                        <span class="rating">
                            <span class="box"></span><span class="box"></span><span class="box"></span><span class="box"></span><span class="box"></span>
                        </span>
                    </div>
                @endfor
            </div>

            <div class="right">
                <div class="observation-title">Beobachtungen</div>
                <div class="faces"><span class="face">:)</span><span class="face">:|</span><span class="face">:(</span></div>
                @for ($i = 0; $i < 4; $i++)
                    <div class="observation-row">
                        <span class="box"></span><span class="box"></span><span class="box"></span>
                    </div>
                @endfor
            </div>
        </div>

        <div class="legend">
            1 = im entwicklungsfaehigen Masse vorhanden &nbsp;&nbsp;
            2 = im erkennbaren Masse vorhanden &nbsp;&nbsp;
            3 = im deutlichen Masse vorhanden<br>
            4 = im hohen Masse vorhanden &nbsp;&nbsp;
            5 = im hoechsten Masse vorhanden
        </div>

        <div class="footer-number">{{ $item['footer_nummer'] }}</div>
    </section>
@endforeach
</body>
</html>
