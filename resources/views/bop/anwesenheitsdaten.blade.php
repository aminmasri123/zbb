<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Anwesenheitsdaten</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f1f3f5; }
    </style>
</head>
<body>
    <h1>Anwesenheitsdaten</h1>
    <p><strong>Schule:</strong> {{ $partner->name }}</p>
    <p><strong>Schuljahr:</strong> {{ $schuljahr }} | <strong>Teil:</strong> {{ $teil }}</p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Vorname</th>
                <th>Nachname</th>
                <th>Klasse</th>
                <th>Anwesenheit</th>
                <th>Bemerkung</th>
            </tr>
        </thead>
        <tbody>
            @forelse($schueler as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->person?->vorname }}</td>
                    <td>{{ $item->person?->nachname }}</td>
                    <td>{{ $item->klasse }}</td>
                    <td></td>
                    <td></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Keine Teilnehmer gefunden.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
