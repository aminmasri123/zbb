<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Einschätzung der Kompetenzen</title>
    <style>
        @page { size: A4 portrait; margin: 7mm 10mm 6mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111827; font-family: "Comic Sans MS", "DejaVu Sans", sans-serif; font-size: 10px; }
        .page { page-break-after: always; width: 190mm; }
        .page:last-child { page-break-after: auto; }
        table { width: 100%; border-collapse: collapse; }
        .header td { border: 0; padding: 0 0 3mm; vertical-align: middle; }
        .logo { width: 27mm; height: auto; }
        .title { color: #f58220; font-size: 23px; font-weight: 700; letter-spacing: .4px; text-align: center; text-shadow: .45px .45px #111827; }
        .meta { background: #fcc419; border: 1px solid #111; padding: 3mm 3mm 2.5mm; }
        .meta-grid td { border: 0; padding: 0 1.2mm 2mm 0; vertical-align: top; }
        .meta-grid td:last-child { padding-right: 0; }
        .meta-label { display: block; margin: 0 0 1mm; font-size: 9px; font-weight: 700; }
        .meta-value { display: block; min-height: 7.5mm; padding: 1.5mm 1.8mm; overflow: hidden; border: 1px solid #111; background: #fff; font-size: 10px; white-space: nowrap; }
        .w-name { width: 27%; } .w-class { width: 13%; } .w-date { width: 20%; }
        .w-staff { width: 30%; } .w-school { width: 32%; } .w-area { width: 38%; }
        .criteria { border-left: 1px solid #111; border-right: 1px solid #111; }
        .criterion { page-break-inside: avoid; }
        .criterion > td { border-bottom: 1px solid #111; padding: 1.15mm 2mm; vertical-align: middle; }
        .criterion .label { width: 61%; padding-left: 8mm; font-size: 10.4px; line-height: 1.23; }
        .criterion .rating { width: 39%; padding: .8mm 2mm; }
        .criterion.final > td { border-top: 2px solid #f58220; }
        .score-box { border: 2px solid #111; border-radius: 8px; background: #fff; }
        .final .score-box { border-width: 3px; }
        .score-box td { width: 20%; padding: .45mm .2mm .25mm; text-align: center; vertical-align: middle; }
        .check { display: inline-block; width: 4.8mm; height: 4.8mm; border: 1px solid #111; border-radius: 3px; font-family: "DejaVu Sans", sans-serif; font-size: 11px; font-weight: 700; line-height: 4.3mm; }
        .star { display: inline-block; color: #f6c515; font-family: "DejaVu Sans", sans-serif; line-height: 1; }
        .s5 { font-size: 18px; } .s4 { font-size: 17px; } .s3 { font-size: 16px; } .s2 { font-size: 15px; } .s1 { font-size: 14px; }
        .legend { margin-top: 2.2mm; text-align: center; font-size: 9px; white-space: nowrap; }
        .legend b { font-size: 9.5px; }
        .legend .star { vertical-align: -1px; margin: 0 1mm .1mm .6mm; }
    </style>
</head>
<body>
@foreach($teilnehmer as $index => $person)
    <section class="page">
        <table class="header"><tr>
            <td style="width: 30mm"><img class="logo" src="{{ public_path('img/logo/logo.png') }}" alt="ZBB"></td>
            <td class="title">Einschätzung der Kompetenzen</td>
        </tr></table>

        <div class="meta">
            <table class="meta-grid"><tr>
                <td class="w-name"><span class="meta-label">Vorname</span><span class="meta-value">{{ $person['vorname'] }}</span></td>
                <td class="w-name"><span class="meta-label">Nachname</span><span class="meta-value">{{ $person['nachname'] }}</span></td>
                <td class="w-class"><span class="meta-label">Klasse</span><span class="meta-value">{{ $person['klasse'] }}</span></td>
                <td class="w-date"><span class="meta-label">Datum</span><span class="meta-value">{{ $person['datum'] }}</span></td>
            </tr></table>
            <table class="meta-grid"><tr>
                <td class="w-staff"><span class="meta-label">Fachkraft</span><span class="meta-value">{{ $person['anleiter_name'] }}</span></td>
                <td class="w-school"><span class="meta-label">Schule</span><span class="meta-value">{{ $person['schule_name'] }}</span></td>
                <td class="w-area"><span class="meta-label">Berufsfeld</span><span class="meta-value">{{ $person['bereich_name'] }}</span></td>
            </tr></table>
        </div>

        <table class="criteria">
            @foreach($config['criteria'] as $criterionIndex => $criterion)
                @php($rating = $person['ratings']->get($criterion['key']))
                <tr class="criterion {{ $loop->last ? 'final' : '' }}">
                    <td class="label">{{ $criterionIndex + 1 }}. {{ $criterion['label'] }}</td>
                    <td class="rating"><table class="score-box">
                        <tr>
                            @foreach([5, 4, 3, 2, 1] as $score)
                                <td><span class="check">{{ (int) ($rating?->bewertung ?? 0) === $score ? 'X' : '' }}</span></td>
                            @endforeach
                        </tr>
                        <tr>
                            @foreach([5, 4, 3, 2, 1] as $score)
                                <td><span class="star s{{ $score }}">★</span></td>
                            @endforeach
                        </tr>
                    </table></td>
                </tr>
            @endforeach
        </table>

        <p class="legend"><b>Legende:</b>
            @foreach([5, 4, 3, 2, 1] as $score)
                “{{ $config['scale'][$score] ?? '' }}” <span class="star s{{ $score }}">★</span>
            @endforeach
        </p>
    </section>
@endforeach
</body>
</html>
