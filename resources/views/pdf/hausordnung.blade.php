<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Hausordnung Teilnehmende BOP">
    <title>Hausordnung</title>
    <style>
        body {
            font-family: "Arial", sans-serif;
            margin: 0;
            padding: 0;
        }

        h1, p {
            margin: 1rem 0;
        }

        .section-title {
            font-size: 16pt;
            text-align: left;
            margin-top: 0px;
            margin-bottom: 0px;
        }

        .list-item {
            font-size: 11pt;
            margin-left: 20px;
            text-align: justify;
            margin-top: 20px;
        }

        .list-item b {
            font-weight: bold;
        }

        .list-item u {
            text-decoration: underline;
        }

        img.logo {
            position: relative;
            top: 25px;
            left: 442px;
            height: 113px;
        }

        .important {
            color: red;
            font-weight: bold;
        }

        ol {
            padding-left: 20px;
        }

        .section-verstaendnis {
            font-size: 11pt;
            font-weight: bold;
            text-align: justify;
            margin: 30 0 40 0;
        }

        .signature-table {
            margin-top: 60px;
            text-align: left;
        }

        .signature-table td {
            text-align: left;
        }

        .line {
            margin-bottom: -5px;
            border-bottom: 1px solid black;
            width: 120px;
            display: inline-block;
        }

        .line-text {
            border-top: 1px solid black;
            padding: 5 10 0 10;
        }

        .spacer {
            width: 80px;
        }

        .db {
            display: block;
            margin-bottom: 5px;
            text-align: center;
            min-height: 20px;
        }

        /* Seitenumbruch nach jedem Teilnehmer */
        .page-break {
            page-break-after: always;
        }
        .page-number {
            text-align: center;
            font-size: 10pt;
            margin-top: 40px;
        }

    </style>
</head>

<body>
    @php
        $klassenzähler = [];
    @endphp

    @foreach ($alle_teilnehmer as $index => $teilnehmer)

        <div>
            <img class="logo" src="{{asset('storage/img/logo.png')}}" alt="zbb_logo">
        </div>

        <div>
            <h1 class="section-title">Hausordnung Teilnehmende BOP</h1>

            <ol>
                <li class="list-item">
                    Von allen Teilnehmern/-innen wird <b>Rücksicht</b> auf andere Teilnehmer und Mitarbeiter erwartet.
                </li>
                <li class="list-item">
                    Das Firmengelände darf während der Arbeitszeit, <b>auch in den Pausen</b>, <u>nicht verlassen werden</u>. Unerlaubtes Entfernen führt zum Verlust des <b>Versicherungsschutzes</b> durch die Berufsgenossenschaft.
                </li>
                <li class="list-item">
                    <b><u>Rauchen</u></b> ist Jugendlichen unter 18 Jahren nicht erlaubt (Jugendschutzgesetz).
                </li>
                <li class="list-item">
                    Die Räumlichkeiten sind sauber zu halten. <span class="important">Es ist strikt verboten, vor und hinter dem Gebäude Gegenstände zu werfen oder Ball zu spielen.</span> Für Schäden haftet der Verursacher.
                </li>
                <li class="list-item">
                    <b>Körperliche Gewalt</b>, verbale Provokationen sowie <b>Rechtsextremismus und Fremdenfeindlichkeit</b> werden <b>nicht toleriert</b>.
                </li>
                <li class="list-item">
                    Abfall ist korrekt zu entsorgen.
                </li>
                <li class="list-item">
                    Aus Datenschutzgründen dürfen <b><u>keine Fotos oder Filme</u></b> von anderen Personen gemacht und verbreitet werden.
                </li>
                <li class="list-item">
                    Während der Arbeitszeit ist der Konsum von <b>Alkohol, Energy-Drinks</b> und anderen suchterzeugenden Substanzen verboten. Handys dürfen nicht genutzt werden.
                </li>
                <li class="list-item">
                    Das Betreten des Spielplatzes vor dem Hauptgebäude ist verboten.
                </li>
                <li class="list-item">
                    Die Toiletten sind sauber zu halten und nicht vorsätzlich zu verunreinigen.
                </li>
                <li class="list-item">
                    In den Werkstätten müssen <b>geschlossene Schuhe</b> getragen werden.
                </li>
            </ol>
            <p class="section-verstaendnis">Der Inhalt der Hausordnung wurde mir erläutert. Ich habe ihn verstanden, zur Kenntnis genommen und werde mich an diese Regeln halten. Mir ist bekannt, dass ein Verstoß gegen diese Hausordnung zum Ausschluss aus dem Berufsorientierungsprogramm führen kann.</p>

            <div>

                <p>Saarbrücken, <span class="line">{{ \Carbon\Carbon::parse($datum)->format('d.m.Y') }}</span></p>
             </div>
            <table class="signature-table">
                <tr>
                    <td>
                        <span class="db">{{ $teilnehmer->person->nachname }}, {{ $teilnehmer->person->vorname }} </span>
                        <span class="line-text">Name der Schülerin/ des Schülers</span>
                    </td>
                    <td class="spacer"></td>
                    <td>
                        <span class="db">{{ $teilnehmer->klasse }}</span>
                        <span class="line-text">Klasse</span>
                    </td>
                    <td class="spacer"></td>
                    <td>
                        <span class="db"></span>
                        <span class="line-text">Unterschrift</span>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Seitennummerierung -->
        @php
            $klasse = $teilnehmer->klasse;
            if (!isset($klassenzähler[$klasse])) {
                $klassenzähler[$klasse] = 1;
            } else {
                $klassenzähler[$klasse]++;
            }
        @endphp
        <div class="page-number">
            {{ $klassenzähler[$klasse] }} (Klasse {{ $klasse }})
        </div>

        <!-- Seitenumbruch nach jedem Teilnehmer -->
        @if ($index < $alle_teilnehmer->count() - 1)
            <div class="page-break"></div>
        @endif
    @endforeach

</body>

</html>
