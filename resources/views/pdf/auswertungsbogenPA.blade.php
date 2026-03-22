<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Auswertungsbogen für die PA">
    <title>PA Auswertungbogen {{$schulname}}_{{$alle_teilnehmer->first()->schuljahr}} Teil_{{$alle_teilnehmer->first()->teil}}</title>
    <style>
        body {
            font-family: "Arial", sans-serif;
            margin: 0;
            padding: 0;
            font-size: 9pt;
            font-weight: bold;
        }
        img{
            image-rendering: auto;
        }
        .page-break {
            page-break-after: always;
        }
        .name{
            position: absolute;
            left: 47;
            top: 25;
        }
        .geschlecht{
            position: absolute;
            left: 80;
            top: 45;
        }
        .schule{
            position: absolute;
            left: 45;
            top: 63;
        }
        .gb{
            position: absolute;
            left: 285;
            top: 25;
            font-size: 9pt;
        }
        .klasse{
            position: absolute;
            left: 285;
            top: 45;
        }
        .agenda{
            font-weight: 100;
        }
        table{
            margin: 0 auto 0 auto;
        }
        td{
            text-align: center;
            width: 100%;
            margin: 0;
            padding: 0;
            line-height: 1;
        }
        .nummer{
            position: absolute;
            left: 50%;
            transform: translate(-50%, -50%);
            padding-top: 35px;

        }
    </style>
</head>
<body>
    @php
    $klasse_counter = []; // Array zur Speicherung des Zählers für jede Klasse
@endphp

@foreach ($alle_teilnehmer as $teilnehmer)
    @php
        // Falls die Klasse noch nicht existiert, initialisiere sie mit 1
        if (!isset($klasse_counter[$teilnehmer->klasse])) {
            $klasse_counter[$teilnehmer->klasse] = 1;
        }
    @endphp


    <p class="name">
        {{ $teilnehmer->person->nachname }}, {{ $teilnehmer->person->vorname }}
    </p>
    <p class="klasse">
        {{ $teilnehmer->klasse }}
    </p>
    <p class="gb">
        {{ \Carbon\Carbon::parse($teilnehmer->geburtsdatum)->format('d.m.y') }}
    </p>
    <p class="geschlecht">
        {{ $teilnehmer->geschlecht }}
    </p>
    <p class="schule">
        {{ $schulname }}
    </p>
    <img src="{{ public_path('img/auswertungPa.png') }}" height="600" width="100%">

    <table class="agenda">
        <tbody>
            <tr>
                <td><p>1 = im entwicklungsfähigen Maße vorhanden</p></td>
                <td><p>&nbsp;&nbsp;&nbsp;&nbsp;2 = im erkennbaren Maße vorhanden &nbsp;&nbsp;&nbsp;&nbsp;</p></td>
                <td><p>3 = im deutlichen Maße vorhanden</p></td>
            </tr>
            <tr>
                <td><p>4 = im hohen Maße vorhanden</p></td>
                <td><p>5 = im höchsten Maße vorhanden</p></td>
                <td><p>&nbsp;</p></td>
            </tr>
            <div class="nummer">
                <p class="">
                    {{ $klasse_counter[$teilnehmer->klasse] }}
                </p>
            </div>
        </tbody>
    </table>

    <!-- Seitenumbruch nach jedem Teilnehmer -->
    <div class="page-break"></div>

    @php
        // Zähler für die aktuelle Klasse erhöhen
        $klasse_counter[$teilnehmer->klasse]++;
    @endphp
@endforeach


</body>

</html>
