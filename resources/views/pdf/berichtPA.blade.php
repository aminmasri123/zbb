<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="{{asset('css/bootstrap.min.css')}}">
    <title>Abschlussbericht PA</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 8mm 10mm 8mm;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            min-width: 0;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            padding: 0;
            margin: 0;
            color: black !important;
        }
        .header {
            text-align: center;
            margin: 0;
        }
        h1{
            font-size: 32pt;
            font-weight: bold;
        }
        .section-title {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: 9.5pt;
        }
        .table th, .table td {
            border: 1px solid black !important;
            padding: 3px;
            text-align: left;
            line-height: 1.2;
            color: black;

        }
        .table th {
            background-color: #cdcdcd;
            font-weight: unset;
        }
        .legend{
            font-size: 16px;
        }
        .legend-text {
            font-size: 8pt;
            margin: 0;
            line-height: 1.3;
        }
        .page-break {
            page-break-after: always;
        }
        .w-col1{
            width: 6cm
        }
        .w-col2{
            width: 0.5cm;
        }
        .h-col1{
            height: 3.3cm !important;
            max-height: 3.3cm !important;
        }
        .h-col2{
            height: 0.3cm !important;
            max-height: 0.3cm !important;
        }
        .section{
            margin-top: 0cm;
        }
    </style>
</head>
    <body>
        @php
            $logoPath = str_replace('\\', '/', public_path('storage/img/logo-hamet-bop.png'));
        @endphp
        <div class="header">
            <table width="100%">
                <tr>
                    <td>
                        <h1>Abschlussbericht</h1>
                    </td>
                    <td style="text-align: right;">
                        <img src="file://{{ $logoPath }}" style="height:2.35cm; width:2.29cm;" alt="Logo-Hamet">
                    </td>
                </tr>
            </table>
        </div>
        <div class="section">
            <h2 class="section-title"></h2>
            <table class="table">
                <tr>
                    <th colspan="2" class="text-bold"><b>1. Stammdaten</b></th>
                </tr>
                <tr>
                    <td style="width: 50%">Vorname: {{ $teilnehmer->vorname }}</td>
                    <td style="width: 50%">Name: {{ $teilnehmer->nachname }}</td>
                </tr>
                <tr>
                    <td style="width: 50%">Schule: {{$teilnehmer->schule->schule}}</td>
                    <td style="width: 50%">Klasse: {{$teilnehmer->klasse}}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <!-- Seite 1 --->
                <table class="table mb-1" >
                    <thead>
                        <tr>
                            <th class="pl-1 pt-1 ml-0 w-col1" style="border-bottom:none !important; border-top:none !important; border-right:none !important; border-left:1px solid #bdbdbd !important;">2. Kompetenzbereiche</th>
                        </tr>
                        <tr>
                            <th class="w-col1 p-0" style="border-bottom:none !important; border-top:none !important; border-right:none !important; border-left:1px solid #bdbdbd !important;"></th>
                            <th class="w-col2 p-0 border-right-0 border-bottom-0" style="border:none !important;"></th>
                            <th class="w-col2 p-1 border-left-0 border-bottom-0 border-right-0 text-center  align-top" style="border:none !important;">1</th>
                            <th class="w-col2 p-1 border-left-0 border-bottom-0 border-right-0 text-center align-top" style="border:none !important;">2</th>
                            <th class="w-col2 p-1 border-left-0 border-bottom-0 border-right-0 text-center align-top" style="border:none !important;">3</th>
                            <th class="w-col2 p-1 border-left-0 border-bottom-0 border-right-0 text-center align-top" style="border:none !important;">4</th>
                            <th class="w-col2 p-1  text-center align-top" style="border:none !important;">5</th>
                            <th class="p-1 pl-2 text-start align-top" style="border:none !important;">Beurteilung</th>
                        </tr>
                        <tr>
                            <th class="w-col1">Soziale Kompetenzen</th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="border-top-0 border-left-0 border-bottom-0"  style="border:none !important;"></th>
                        </tr>
                    </thead>
                    <tbody>

                        <tr>
                            <td class="w-col1" rowspan="2">Kommunikationsfähigkeit</td>
                            <td class="w-col2 h-col1 text-center">TL</td>
                            <td class="w-col2 h-col1 text-center">{{$teilnehmer->auswertungPa->kommunikation == 1 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col1 text-center">{{$teilnehmer->auswertungPa->kommunikation == 2 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col1 text-center">{{$teilnehmer->auswertungPa->kommunikation == 3 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col1 text-center">{{$teilnehmer->auswertungPa->kommunikation == 4 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col1 text-center">{{$teilnehmer->auswertungPa->kommunikation == 5 ? 'X' : ''}}</td>
                            <td class="border-bottom-0 h-col1">{{ $beurteilungen['kommunikationsfaehigkeit'][$teilnehmer->auswertungPa->kommunikation] ?? '' }}</td>
                        </tr>

                        <tr>
                            <td class="w-col2 h-col2 text-center">SE</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->kommunikation == 1 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->kommunikation == 2 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->kommunikation == 3 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->kommunikation == 4 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->kommunikation == 5 ? 'X' : ''}}</td>
                            <td class="border-top-0"></td>
                        </tr>
                        <tr>
                            <td class="w-col1" rowspan="2">Teamfähigkeit (Verträglichkeit, Kooperationsfähigkeit)</td>
                            <td class="w-col2 h-col1 text-center">TL</td>
                            <td class="w-col2 h-col1 text-center">{{$teilnehmer->auswertungPa->teamfaehigkeit == 1 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col1 text-center">{{$teilnehmer->auswertungPa->teamfaehigkeit == 2 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col1 text-center">{{$teilnehmer->auswertungPa->teamfaehigkeit == 3 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col1 text-center">{{$teilnehmer->auswertungPa->teamfaehigkeit == 4 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col1 text-center">{{$teilnehmer->auswertungPa->teamfaehigkeit == 5 ? 'X' : ''}}</td>
                            <td class="border-bottom-0 h-col1">{{ $beurteilungen['teamwork'][$teilnehmer->auswertungPa->teamfaehigkeit] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="w-col2 h-col2 text-center">SE</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->teamfaehigkeit == 1 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->teamfaehigkeit == 2 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->teamfaehigkeit == 3 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->teamfaehigkeit == 4 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->teamfaehigkeit == 5 ? 'X' : ''}}</td>
                            <td class="border-top-0"></td>
                        </tr>
                        <tr>
                            <td class="w-col1" rowspan="2">Umgangsformen</td>
                            <td class="w-col2 h-col1 text-center">TL</td>
                            <td class="w-col2 h-col1 text-center">{{$teilnehmer->auswertungPa?->umgangsformen == 1 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col1 text-center">{{$teilnehmer->auswertungPa?->umgangsformen == 2 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col1 text-center">{{$teilnehmer->auswertungPa?->umgangsformen == 3 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col1 text-center">{{$teilnehmer->auswertungPa?->umgangsformen == 4 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col1 text-center">{{$teilnehmer->auswertungPa?->umgangsformen == 5 ? 'X' : ''}}</td>
                            <td class="border-bottom-0 h-col1">{{ $beurteilungen['umgangsformen'][$teilnehmer->auswertungPa?->umgangsformen] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="w-col2 h-col2 text-center">SE</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->umgangsformen == 1 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->umgangsformen == 2 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->umgangsformen == 3 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->umgangsformen == 4 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->umgangsformen == 5 ? 'X' : ''}}</td>
                            <td class="border-top-0"></td>
                        </tr>
                    </tbody>
                </table>

                <table class="table" >
                    <thead>
                        <tr>
                            <th class="w-col1">Personale Kompetenzen</th>
                           <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="border-top-0 border-left-0 border-bottom-0"  style="border:none !important;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="w-col1" rowspan="2">Durchhaltevermögen (Belastbarkeit)</td>
                            <td class="w-col2 text-center h-col1">TL</td>
                            <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->durchhaltevermoegen == 1 ? 'X' : ''}}</td>
                            <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->durchhaltevermoegen == 2 ? 'X' : ''}}</td>
                            <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->durchhaltevermoegen == 3 ? 'X' : ''}}</td>
                            <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->durchhaltevermoegen == 4 ? 'X' : ''}}</td>
                            <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->durchhaltevermoegen == 5 ? 'X' : ''}}</td>
                            <td class="border-bottom-0 h-col1">{{ $beurteilungen['durchhaltevermögen'][$teilnehmer->auswertungPa?->durchhaltevermoegen] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="w-col2 h-col2 text-centerr">SE</td>
                            <td class="h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->durchhaltevermoegen == 1 ? 'X' : ''}}</td>
                            <td class="h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->durchhaltevermoegen == 2 ? 'X' : ''}}</td>
                            <td class="h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->durchhaltevermoegen == 3 ? 'X' : ''}}</td>
                            <td class="h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->durchhaltevermoegen == 4 ? 'X' : ''}}</td>
                            <td class="h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->durchhaltevermoegen == 5 ? 'X' : ''}}</td>
                            <td class="border-top-0 h-col2"></td>
                        </tr>
                    </tbody>
                </table>
            <!-- End Seite 1 --->
            <div class="page-break"></div>
            <!-- Seite 2 --->
                <div class="header m-0">
                    <table width="100%">
                        <tr>
                            <td>
                                <h1>Abschlussbericht</h1>
                            </td>
                            <td style="text-align: right;">
                                <img src="file://{{ $logoPath }}" style="height:2.35cm; width:2.29cm;" alt="Logo-Hamet">
                            </td>
                        </tr>
                    </table>
                </div>
                <table class="table m-0" style="margin-top:5px !important">
                    <thead>
                        <tr>
                            <th class="pl-1 pt-1 ml-0 w-col1" style="border-bottom:none !important; border-top:none !important; border-right:none !important; border-left:1px solid #bdbdbd !important;">2. Kompetenzbereiche</th>
                        </tr>
                         <tr>
                            <th class="w-col1 p-0" style="border-bottom:none !important; border-top:none !important; border-right:none !important; border-left:1px solid #bdbdbd !important;"></th>
                            <th class="w-col2 p-0 border-right-0 border-bottom-0" style="border:none !important;"></th>
                            <th class="w-col2 p-1 border-left-0 border-bottom-0 border-right-0 text-center  align-top" style="border:none !important;">1</th>
                            <th class="w-col2 p-1 border-left-0 border-bottom-0 border-right-0 text-center align-top" style="border:none !important;">2</th>
                            <th class="w-col2 p-1 border-left-0 border-bottom-0 border-right-0 text-center align-top" style="border:none !important;">3</th>
                            <th class="w-col2 p-1 border-left-0 border-bottom-0 border-right-0 text-center align-top" style="border:none !important;">4</th>
                            <th class="w-col2 p-1  text-center align-top" style="border:none !important;">5</th>
                            <th class="p-1 pl-2 text-start align-top" style="border:none !important;">Beurteilung</th>
                        </tr>
                        <tr>
                            <th class="w-col1">Personale Kompetenzen</th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="border-top-0 border-left-0 border-bottom-0"  style="border:none !important;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="w-col1" rowspan="2">Motivation (Leistungs- und
                                Anstrengungsbereitschaft)
                            </td>
                            <td class="w-col2 text-center h-col1">TL</td>
                            <td class="w-col2 text-center">{{$teilnehmer->auswertungPa?->motivation_leistungsbereitschaft == 1 ? 'X' : ''}}</td>
                            <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->motivation_leistungsbereitschaft == 2 ? 'X' : ''}}</td>
                            <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->motivation_leistungsbereitschaft == 3 ? 'X' : ''}}</td>
                            <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->motivation_leistungsbereitschaft == 4 ? 'X' : ''}}</td>
                            <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->motivation_leistungsbereitschaft == 5 ? 'X' : ''}}</td>
                            <td class="border-bottom-0">{{ $beurteilungen['motivation'][$teilnehmer->auswertungPa->motivation_leistungsbereitschaft] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="w-col2 h-col2 text-center">SE</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->motivation_leistungsbereitschaft == 1 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->motivation_leistungsbereitschaft == 2 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->motivation_leistungsbereitschaft == 3 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->motivation_leistungsbereitschaft == 4 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->motivation_leistungsbereitschaft == 5 ? 'X' : ''}}</td>
                            <td class="border-top-0 "></td>
                        </tr>
                        <tr>
                            <td class="w-col1" rowspan="2">Sorgfalt (Genauigkeit)</td>
                            <td class="w-col2 text-center h-col1">TL</td>
                            <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->sorgfalt == 1 ? 'X' : ''}}</td>
                            <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->sorgfalt == 2 ? 'X' : ''}}</td>
                            <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->sorgfalt == 3 ? 'X' : ''}}</td>
                            <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->sorgfalt == 4 ? 'X' : ''}}</td>
                            <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->sorgfalt == 5 ? 'X' : ''}}</td>
                            <td class="border-bottom-0">{{ $beurteilungen['sorgfalt'][$teilnehmer->auswertungPa?->durchhaltevermoegen] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="w-col2 h-col2 text-center">SE</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->sorgfalt == 1 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->sorgfalt == 2 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->sorgfalt == 3 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->sorgfalt == 4 ? 'X' : ''}}</td>
                            <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->sorgfalt == 5 ? 'X' : ''}}</td>
                            <td class=""></td>
                        </tr>
                    </tbody>
                </table>
                <!-- Methodische Kompetenzen -->
                <table class="table m-0">
                    <thead>
                        <tr>
                            <th class="w-col1">Methodische Kompetenzen</th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="border-top-0 border-left-0 border-bottom-0"  style="border:none !important;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="w-col1" rowspan="2">Analyse- und Problemlösefähigkeit</td>
                            <td class="text-center h-col1">TL</td>
                            <td class="text-center h-col1">{{$teilnehmer->auswertungPa?->analyse_problemloesefaehigkeit == 1 ? 'X' : ''}}</td>
                            <td class="text-center h-col1">{{$teilnehmer->auswertungPa?->analyse_problemloesefaehigkeit == 2 ? 'X' : ''}}</td>
                            <td class="text-center h-col1">{{$teilnehmer->auswertungPa?->analyse_problemloesefaehigkeit == 3 ? 'X' : ''}}</td>
                            <td class="text-center h-col1">{{$teilnehmer->auswertungPa?->analyse_problemloesefaehigkeit == 4 ? 'X' : ''}}</td>
                            <td class="text-center h-col1">{{$teilnehmer->auswertungPa?->analyse_problemloesefaehigkeit == 5 ? 'X' : ''}}</td>
                            <td class="border-bottom-0">{{ $beurteilungen['analyse'][$teilnehmer->auswertungPa?->analyse_problemloesefaehigkeit] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="w-col2 h-col2 text-center">SE</td>
                            <td class="text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->analyse_problemloesefaehigkeit == 1 ? 'X' : ''}}</td>
                            <td class="text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->analyse_problemloesefaehigkeit == 2 ? 'X' : ''}}</td>
                            <td class="text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->analyse_problemloesefaehigkeit == 3 ? 'X' : ''}}</td>
                            <td class="text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->analyse_problemloesefaehigkeit == 4 ? 'X' : ''}}</td>
                            <td class="text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->analyse_problemloesefaehigkeit == 5 ? 'X' : ''}}</td>
                            <td class="border-top-0"></td>
                        </tr>
                        <tr>
                            <td class="w-col1" rowspan="2">Arbeitsplanung</td>
                            <td class="text-center h-col1">TL</td>
                            <td class="text-center h-col1">{{$teilnehmer->auswertungPa?->arbeitsplanung == 1 ? 'X' : ''}}</td>
                            <td class="text-center h-col1">{{$teilnehmer->auswertungPa?->arbeitsplanung == 2 ? 'X' : ''}}</td>
                            <td class="text-center h-col1">{{$teilnehmer->auswertungPa?->arbeitsplanung == 3 ? 'X' : ''}}</td>
                            <td class="text-center h-col1">{{$teilnehmer->auswertungPa?->arbeitsplanung == 4 ? 'X' : ''}}</td>
                            <td class="text-center h-col1">{{$teilnehmer->auswertungPa?->arbeitsplanung == 5 ? 'X' : ''}}</td>
                            <td class="border-bottom-0">{{ $beurteilungen['arbeitsplanung'][$teilnehmer->auswertungPa?->arbeitsplanung] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="w-col2 h-col2 text-center">SE</td>
                            <td class="text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->arbeitsplanung == 1 ? 'X' : ''}}</td>
                            <td class="text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->arbeitsplanung == 2 ? 'X' : ''}}</td>
                            <td class="text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->arbeitsplanung == 3 ? 'X' : ''}}</td>
                            <td class="text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->arbeitsplanung == 4 ? 'X' : ''}}</td>
                            <td class="text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->arbeitsplanung == 1 ? 'X' : ''}}</td>
                            <td class="border-top-0 h-col2"></td>
                        </tr>
                    </tbody>
                </table>
                <table class="table m-0">
                    <thead>
                        <tr>
                            <th class="w-col1">Berufsübergreifende Kompetenzen</th>
                           <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="border-top-0 border-left-0 border-bottom-0"  style="border:none !important;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="w-col1" rowspan="2">Feinmotorik</td>
                            <td class="text-center h-col1">TL</td>
                            <td class="text-center h-col1">{{$teilnehmer->auswertungPa?->feinmotorik == 1 ? 'X' : ''}}</td>
                            <td class="text-center h-col1">{{$teilnehmer->auswertungPa?->feinmotorik == 2 ? 'X' : ''}}</td>
                            <td class="text-center h-col1">{{$teilnehmer->auswertungPa?->feinmotorik == 3 ? 'X' : ''}}</td>
                            <td class="text-center h-col1">{{$teilnehmer->auswertungPa?->feinmotorik == 4 ? 'X' : ''}}</td>
                            <td class="text-center h-col1">{{$teilnehmer->auswertungPa?->feinmotorik == 5 ? 'X' : ''}}</td>
                            <td class="border-bottom-0">{{ $beurteilungen['feinmotorik'][$teilnehmer->auswertungPa?->feinmotorik] ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="w-col2 h-col2 text-center">SE</td>
                            <td class="text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->feinmotorik == 1 ? 'X' : ''}}</td>
                            <td class="text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->feinmotorik == 2 ? 'X' : ''}}</td>
                            <td class="text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->feinmotorik == 3 ? 'X' : ''}}</td>
                            <td class="text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->feinmotorik == 4 ? 'X' : ''}}</td>
                            <td class="text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->feinmotorik == 5 ? 'X' : ''}}</td>
                            <td class="border-top-0"></td>
                        </tr>
                    </tbody>
                </table>
            <!-- End Seite 2 --->
            <div class="page-break"></div>
            <!-- Seite 3 --->
            <div class="header m-0">
                <table width="100%">
                    <tr>
                        <td>
                            <h1>Abschlussbericht</h1>
                        </td>
                        <td style="text-align: right;">
                            <img src="file://{{ $logoPath }}" style="height:2.35cm; width:2.29cm;" alt="Logo-Hamet">
                        </td>
                    </tr>
                </table>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th class="pl-1 pt-1 ml-0 w-col1" style="border-bottom:none !important; border-top:none !important; border-right:none !important; border-left:1px solid #bdbdbd !important;">2. Kompetenzbereiche</th>
                    </tr>
                     <tr>
                        <th class="w-col1 p-0" style="border-bottom:none !important; border-top:none !important; border-right:none !important; border-left:1px solid #bdbdbd !important;"></th>
                        <th class="w-col2 p-0 border-right-0 border-bottom-0" style="border:none !important;"></th>
                        <th class="w-col2 p-1 border-left-0 border-bottom-0 border-right-0 text-center  align-top" style="border:none !important;">1</th>
                        <th class="w-col2 p-1 border-left-0 border-bottom-0 border-right-0 text-center align-top" style="border:none !important;">2</th>
                        <th class="w-col2 p-1 border-left-0 border-bottom-0 border-right-0 text-center align-top" style="border:none !important;">3</th>
                        <th class="w-col2 p-1 border-left-0 border-bottom-0 border-right-0 text-center align-top" style="border:none !important;">4</th>
                        <th class="w-col2 p-1  text-center align-top" style="border:none !important;">5</th>
                        <th class="p-1 pl-2 text-start align-top" style="border:none !important;">Beurteilung</th>
                    </tr>
                    <tr>
                        <th class="w-col1">Berufsübergreifende Kompetenzen</th>
                        <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="w-col2 border-0" style="border:none !important;"></th>
                            <th class="border-top-0 border-left-0 border-bottom-0" style="border:none !important;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="w-col1" rowspan="2">Grobmotorik</td>
                        <td class="w-col2 text-center h-col1">TL</td>
                        <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->grobmotorik == 1 ? 'X' : ''}}</td>
                        <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->grobmotorik == 2 ? 'X' : ''}}</td>
                        <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->grobmotorik == 3 ? 'X' : ''}}</td>
                        <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->grobmotorik == 4 ? 'X' : ''}}</td>
                        <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->grobmotorik == 5 ? 'X' : ''}}</td>
                        <td class="border-bottom-0">{{ $beurteilungen['grobmotorik'][$teilnehmer->auswertungPa?->grobmotorik] ?? '' }}</td>
                    </tr>
                    <tr>
                        <td class="w-col2 h-col2 text-center">SE</td>
                        <td class="w-col2 h-col2text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->grobmotorik == 1 ? 'X' : ''}}</td>
                        <td class="w-col2 h-col2text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->grobmotorik == 2 ? 'X' : ''}}</td>
                        <td class="w-col2 h-col2text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->grobmotorik == 3 ? 'X' : ''}}</td>
                        <td class="w-col2 h-col2text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->grobmotorik == 4 ? 'X' : ''}}</td>
                        <td class="w-col2 h-col2text-center h-col2">{{$teilnehmer->selbsteinschaetzung?->grobmotorik == 5 ? 'X' : ''}}</td>
                        <td class="border-top-0"></td>
                    </tr>
                    <tr>
                        <td class="w-col1" rowspan="2">Wahrnehmung und Symmetrie</td>
                        <td class="w-col2 text-center h-col1">TL</td>
                        <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->wahrnehmung_symmetrie == 1 ? 'X' : ''}}</td>
                        <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->wahrnehmung_symmetrie == 2 ? 'X' : ''}}</td>
                        <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->wahrnehmung_symmetrie == 3 ? 'X' : ''}}</td>
                        <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->wahrnehmung_symmetrie == 4 ? 'X' : ''}}</td>
                        <td class="w-col2 text-center h-col1">{{$teilnehmer->auswertungPa?->wahrnehmung_symmetrie == 5 ? 'X' : ''}}</td>
                        <td class="border-bottom-0">{{ $beurteilungen['wahrnehmung'][$teilnehmer->auswertungPa?->wahrnehmung_symmetrie] ?? '' }}</td>
                    </tr>
                    <tr>
                        <td class="w-col2 h-col2 text-center">SE</td>
                        <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->wahrnehmung_symmetrie == 1 ? 'X' : ''}}</td>
                        <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->wahrnehmung_symmetrie == 2 ? 'X' : ''}}</td>
                        <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->wahrnehmung_symmetrie == 3 ? 'X' : ''}}</td>
                        <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->wahrnehmung_symmetrie == 4 ? 'X' : ''}}</td>
                        <td class="w-col2 h-col2 text-center">{{$teilnehmer->selbsteinschaetzung?->wahrnehmung_symmetrie == 5 ? 'X' : ''}}</td>
                        <td class=""></td>
                    </tr>
                </tbody>
            </table>
            <div style="border:1px solid black; padding:0.2cm; margin:0;">
                <p style="margin:0 0 3px 0;"><b>Zusammenfassung:</b></p>
                <div style="white-space: pre-wrap; word-wrap: break-word; line-height:1.2;">{{ ltrim($teilnehmer->zusammenfassung) }}</div>
            </div>
            <div class="section ">
                <p class="legend mt-2 mb-0">Legende:</p>
                <p class="legend-text">
                    1 = im entwicklungsfähigem Maße vorhanden, 2 = im erkennbaren Maße vorhanden, 3 = im deutlichen Maße vorhanden
                </p>
                <p class="legend-text">
                    4 = im hohen Maße vorhanden, 5 = im höchsten Maße vorhanden, TL = Testleitung, SE = Selbsteinschätzung
                </p>
            </div>
            <!-- End Seite 3 --->
            <div class="page-break"></div>

            <!-- Seite 4 --->
                <div class="header m-0">
                    <table width="100%">
                        <tr>
                            <td>
                                <h1>Abschlussbericht</h1>
                            </td>
                            <td style="text-align: right;">
                                <img src="file://{{ $logoPath }}" style="height:2.35cm; width:2.29cm;" alt="Logo-Hamet">
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="section">
                    <h2 class="section-title"></h2>
                    <table class="table">
                        <tr>
                            <th colspan="2" class="text-bold"><b>1. Stammdaten</b></th>
                        </tr>
                        <tr>
                            <td style="width: 50%">Vorname: {{ $teilnehmer->vorname }}</td>
                            <td style="width: 50%">Name: {{ $teilnehmer->nachname }}</td>
                        </tr>
                        <tr>
                            <td style="width: 50%">Schule: {{$teilnehmer->schule->schule}}</td>
                            <td style="width: 50%">Klasse: {{$teilnehmer->klasse}}</td>
                        </tr>
                    </table>
                </div>
                <div class="section">
                    <table class="table m-0" style="width: 13.5cm; margin-bottom:5px !important;">
                        <thead>
                            <tr>
                                <td class=" font-weight-bolder" style="font-size: 18px" colspan="8">2. Berufsübergreifende Anforderungen</td>
                            </tr>
                            <tr>
                                <td style="width: 7cm"></td>
                                <td colspan="2"></td>
                                <td class=" text-center fw-bold" colspan="5"><span class="font-weight-bolder" style="font-size:13px">Einschätzung</span><br/>(Erklärung siehe unten) </td>
                            </tr>
                            <tr style="font-size: 16px">
                                <td class=" font-weight-bolder" >Aufgaben</td>
                                <td class="" colspan="2"></td>
                                <td style="width: 0.7cm" class="text-center font-weight-bolder">1</td>
                                <td style="width: 0.7cm" class="text-center font-weight-bolder">2</td>
                                <td style="width: 0.7cm" class="text-center font-weight-bolder">3</td>
                                <td style="width: 0.7cm" class="text-center font-weight-bolder">4</td>
                                <td style="width: 0.7cm" class="text-center font-weight-bolder">5</td>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="pt-0 pb-1" colspan="8"></td>
                            </tr>
                            @foreach ($teilnehmer->uebungen->where('auswertbar', '1') as $uebung )
                                <tr>
                                    <td class="lh-1" style="line-height:1">{{$uebung->name}}</td>
                                    <td class="lh-1" style="width: 1.33cm; line-height:1">Punkte:</td>
                                    <td class="lh-1 text-right" style="width: 1.33cm; line-height:1">{{ $uebung->pivot->punkte }}</td>
                                    @php
                                        $hoechstwert = $uebung->hoechstwert ?: 1; // Falls hoechstwert 0 oder null ist, wird 1 verwendet
                                        $verhaeltnis = $uebung->pivot->punkte / $hoechstwert;
                                    @endphp
                                    <td class="text-center" style="line-height:1">{{ $verhaeltnis <= 0.02275 ? 'X'  : ''}}</td>
                                    <td class="text-center" style="line-height:1">{{ $verhaeltnis > 0.02275 && $verhaeltnis <= 0.15865 ? 'X' : ''}}</td>
                                    <td class="text-center" style="line-height:1">{{ $verhaeltnis > 0.15865 && $verhaeltnis <= 0.845135 ? 'X' : ''}}</td>
                                    <td class="text-center" style="line-height:1">{{ $verhaeltnis > 0.845135 && $verhaeltnis <= 0.97725 ? 'X' : ''}}</td>
                                    <td class="text-center" style="line-height:1">{{ $verhaeltnis > 0.97725 && $verhaeltnis <= 1 ? 'X' : ''}}</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td style="line-height:1">Zeit (s):</td>
                                    <td class="text-right" style="line-height:1">{{ substr($uebung->pivot->zeit, -5) }}</td>
                                    <td style="border-left:none !important; border-right:none !important; line-height:1"></td>
                                    <td style="border-left:none !important; border-right:none !important; line-height:1"></td>
                                    <td style="border-left:none !important; border-right:none !important; line-height:1"></td>
                                    <td style="border-left:none !important; border-right:none !important; line-height:1"></td>
                                    <td style="border-left:none !important;"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="section m-0">
                    <p class="legend mb-1">Legende:</p>
                        <p class="legend-text">1 = im entwicklungsfähigem Maße vorhanden, 2 = im erkennbaren Maße vorhanden </p>
                        <p class="legend-text"> 3 = im deutlichen Maße vorhanden 4 = im hohen Maße vorhanden, 5 = im höchsten Maße vorhanden</p>
                </div>
            <!-- End Seite 4 --->
        </div>
        <script src="{{asset('js/bootstrap.min.js')}}"></script>
    </body>
</html>
