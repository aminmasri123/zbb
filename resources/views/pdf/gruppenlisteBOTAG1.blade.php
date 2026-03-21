<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Teilnehmerliste</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h2 { margin:0 0 10px 0; font-size: 25px; }
        p { margin:0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #444; padding: 4px; text-align: left; }
        th { background-color: #f0f0f0; }

        /* Tabelle für den Header */
        .header-table {
            width: 100%;
            margin-bottom: 15px;
        }
        .header-table td {
            vertical-align: top; /* Text oben ausrichten */
            border: none;
        }
        .logo {
            width: 3cm;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 80%; vertical-align: top;">
                <h2>Teilnehmerliste für den Rolltag</h2>
                <p>
                    <span><strong>Schule:</strong> {{ $schule }}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                    <span style="margin-left: 80px;"><strong>Termin:</strong> {{ $termin }}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                    <span style="margin-left: 80px;"><strong>Gesamtanzahl:</strong> {{ count($gruppenListe) }}</span>
                </p>

            </td>
            <td style="text-align: right; width: 20%;"><img src="{{ asset('storage/img/logo.png') }}" alt="Logo-ZBB" class="logo"></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Nr.</th>
                <th>Nachname</th>
                <th>Vorname</th>
                <th>Klasse</th>
                <th>Geschlecht</th>
                <th>Gruppe</th>
                <th>Bereich</th>
            </tr>
        </thead>
        <tbody>
            @foreach($gruppenListe as $index => $teilnehmer)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $teilnehmer['nachname'] }}</td>
                    <td>{{ $teilnehmer['vorname'] }}</td>
                    <td>{{ $teilnehmer['klasse'] }}</td>
                    <td>{{ $teilnehmer['geschlecht'] }}</td>
                    <td>{{ $teilnehmer['gruppe'] }}</td>
                    <td>{{ $teilnehmer['bereich'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
