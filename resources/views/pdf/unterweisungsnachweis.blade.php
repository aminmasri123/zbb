<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Unterweisungsnachweis</title>
    <style>
        @page { margin: 25px 42px 35px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 10px; }
        h1 { font-size: 21px; font-weight: 500; line-height: 1.35; margin: 0 0 16px; }
        .accent { color: #2563a6; font-size: 12px; margin: 12px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #1f2937; padding: 5px 7px; vertical-align: middle; }
        .meta-label { width: 38%; font-weight: 600; }
        .signature-cell { height: 48px; }
        .signature { max-height: 38px; max-width: 180px; vertical-align: middle; margin-left: 12px; }
        .topics td { width: 33.333%; padding: 3px 5px; }
        .box { display: inline-block; width: 11px; height: 11px; line-height: 10px; margin-right: 4px; border: 1px solid #111; text-align: center; font-weight: bold; }
        .participants th { background: #f3f4f6; text-align: left; }
        .participants td { height: 27px; }
        .participants .name { width: 50%; }
        .footer { position: fixed; left: 0; right: 0; bottom: -22px; text-align: center; font-size: 8px; color: #4b5563; }
    </style>
</head>
<body>
    <div class="footer">Unterweisungsnachweis · Bereich {{ $gruppe->bereich?->name }} · Gruppe {{ $gruppe->id }}</div>

    <h1>Unterweisungsnachweis nach §6 ArbStättV und<br>§4 DGUV Vorschrift 1<br>{{ $gruppe->bereich?->name }}</h1>

    <table>
        <tr>
            <td class="meta-label">Ort und Datum der Unterweisung</td>
            <td>{{ $ort ?: '—' }} · {{ $datum }}</td>
        </tr>
        <tr>
            <td class="meta-label">Unterweisende/r (Vorname, Name, Unterschrift)</td>
            <td class="signature-cell">
                {{ $anleiter }}
                <img class="signature" src="{{ $unterschriftDataUrl }}" alt="Unterschrift">
            </td>
        </tr>
    </table>

    <div class="accent">Themen:</div>
    <table class="topics">
        @foreach($themen->chunk(3) as $zeile)
            <tr>
                @foreach($zeile as $key => $label)
                    <td><span class="box">{{ $ausgewaehlteThemen->contains($key) ? 'X' : '' }}</span>{{ $label }}</td>
                @endforeach
                @for($i = $zeile->count(); $i < 3; $i++)<td></td>@endfor
            </tr>
        @endforeach
    </table>

    <div class="accent">Unterwiesene: {{ $teilnehmer->first()['schule'] ?: ($gruppe->projekt?->name ?? '') }}</div>
    <table class="participants">
        <thead><tr><th class="name">Vorname, Name</th><th>Unterschrift</th></tr></thead>
        <tbody>
            @foreach($teilnehmer as $person)
                <tr><td>{{ $person['voller_name'] }}</td><td></td></tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
