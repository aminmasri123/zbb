<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Zertifikate POBO</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        .page { page-break-after: always; padding: 48px; text-align: center; }
        h1 { font-size: 32px; margin-top: 80px; }
        .name { font-size: 28px; font-weight: bold; margin: 40px 0; }
        .meta { margin-top: 40px; }
    </style>
</head>
<body>
@foreach($schueler as $item)
    <section class="page">
        <h1>Zertifikat POBO</h1>
        <p>Hiermit wird bescheinigt, dass</p>
        <div class="name">{{ $item->person?->vorname }} {{ $item->person?->nachname }}</div>
        <p>an den praxisorientierten Berufsorientierungstagen teilgenommen hat.</p>
        <div class="meta">
            <p>{{ $partner->name }}</p>
            <p>Schuljahr {{ $schuljahr }} | Teil {{ $teil }} | Klasse {{ $item->klasse }}</p>
        </div>
    </section>
@endforeach
</body>
</html>
