<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Auswertung POBO</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 6px; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h1>Auswertung POBO</h1>
    <p>{{ $partner->name }} | {{ $schuljahr }} | Teil {{ $teil }}</p>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Teilnehmer</th>
                <th>Klasse</th>
                <th>Runde 1</th>
                <th>Runde 2</th>
                <th>Runde 3</th>
                <th>Bemerkung</th>
            </tr>
        </thead>
        <tbody>
            @foreach($schueler as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->person?->nachname }}, {{ $item->person?->vorname }}</td>
                    <td>{{ $item->klasse }}</td>
                    <td>{{ $item->einteilungen->firstWhere('runde', 1)?->bereich_id }}</td>
                    <td>{{ $item->einteilungen->firstWhere('runde', 2)?->bereich_id }}</td>
                    <td>{{ $item->einteilungen->firstWhere('runde', 3)?->bereich_id }}</td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
