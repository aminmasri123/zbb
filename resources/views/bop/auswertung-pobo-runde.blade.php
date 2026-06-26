<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Auswertung POBO Runde</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 6px; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h1>Auswertung POBO Runde {{ $runde }}</h1>
    <p>{{ $partner->name }} | {{ $schuljahr }} | Teil {{ $teil }}</p>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Teilnehmer</th>
                <th>Klasse</th>
                <th>Runde</th>
                <th>Auswertung</th>
            </tr>
        </thead>
        <tbody>
            @foreach($schueler as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->person?->nachname }}, {{ $item->person?->vorname }}</td>
                    <td>{{ $item->klasse }}</td>
                    <td>{{ $runde }}</td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
