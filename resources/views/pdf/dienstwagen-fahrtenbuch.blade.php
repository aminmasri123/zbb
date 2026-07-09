<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 4px;
        }

        .meta {
            margin-bottom: 16px;
            color: #4b5563;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #d1d5db;
            padding: 5px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            font-weight: 700;
            text-align: left;
        }

        .right {
            text-align: right;
        }
    </style>
</head>
<body>
    <h1>Fahrtenbuch</h1>
    <div class="meta">
        Fahrzeug: {{ $vehicle?->kennzeichen ?? 'Alle Fahrzeuge' }}
        @if($month)
            | Monat: {{ $month }}
        @endif
        | Erstellt am: {{ now()->format('d.m.Y H:i') }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Datum</th>
                <th>Fahrzeug</th>
                <th>Fahrer</th>
                <th>Startort</th>
                <th>Ziel</th>
                <th class="right">Start km</th>
                <th class="right">Ende km</th>
                <th class="right">Distanz</th>
                <th>Art</th>
                <th>Zweck / Partner</th>
            </tr>
        </thead>
        <tbody>
            @forelse($entries as $entry)
                <tr>
                    <td>{{ optional($entry->date)->format('d.m.Y') }}</td>
                    <td>{{ $entry->dienstwagen?->kennzeichen }}</td>
                    <td>{{ trim(($entry->fahrer?->nachname ?? '') . ' ' . ($entry->fahrer?->vorname ?? '')) }}</td>
                    <td>{{ $entry->startort }}</td>
                    <td>{{ $entry->ziel }}</td>
                    <td class="right">{{ number_format($entry->start_km, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($entry->end_km, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($entry->end_km - $entry->start_km, 0, ',', '.') }}</td>
                    <td>{{ $entry->fahrtart }}</td>
                    <td>
                        {{ $entry->zweck }}
                        @if($entry->geschaeftspartner)
                            <br>{{ $entry->geschaeftspartner }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">Keine Fahrten vorhanden.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
