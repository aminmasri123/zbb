<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>{{ $luv->typ }}-LuV</title>
    <style>
        @page { margin: 16mm 15mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 9.2pt; line-height: 1.35; }
        h1 { margin: 0; font-size: 16pt; }
        h2 { margin: 12px 0 6px; padding: 6px 8px; background: #dbe5f1; border: 1px solid #94a3b8; font-size: 11pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #94a3b8; padding: 6px 8px; vertical-align: top; }
        th { width: 28%; text-align: left; background: #eef2f7; }
        .meta { margin-top: 8px; color: #475569; }
        .status { display: inline-block; margin-left: 8px; padding: 2px 7px; border-radius: 10px; background: #e2e8f0; font-size: 8pt; }
        .section { white-space: pre-line; border: 1px solid #cbd5e1; padding: 9px; min-height: 28px; }
        .section.claim-list { white-space: normal; }
        .claim { margin: 0 0 7px; }
        .claim:last-child { margin-bottom: 0; }
        .missing { color: #92400e; }
        .sources { margin-top: 3px; color: #64748b; font-size: 7.5pt; }
        .warning { margin: 6px 0; padding: 7px; border: 1px solid #f59e0b; background: #fffbeb; }
        .approval-table { page-break-inside: avoid; }
        .footer { margin-top: 22px; border-top: 1px solid #94a3b8; padding-top: 8px; font-size: 8pt; color: #475569; }
    </style>
</head>
<body>
    <h1>Leistungs- und Verhaltensbeurteilung</h1>
    <div class="meta">
        <strong>{{ $project?->name }}</strong> · {{ $luv->typ }}-LuV · Version {{ $luv->version }}
        <span class="status">{{ ['draft' => 'ENTWURF', 'reviewed' => 'FACHLICH GEPRÜFT', 'approved' => 'FREIGEGEBEN'][$luv->status] ?? strtoupper($luv->status) }}</span><br>
        Berichtszeitraum: {{ $luv->von?->format('d.m.Y') }} bis {{ $luv->bis?->format('d.m.Y') }}
        @if($luv->form_version) · Formularversion: {{ $luv->form_version }} @endif
    </div>

    <table class="approval-table">
        <tr><th>Vorname</th><td>{{ $participant?->vorname }}</td></tr>
        <tr><th>Nachname</th><td>{{ $participant?->nachname }}</td></tr>
        <tr><th>Kundennummer</th><td>{{ $participant?->sozialedaten?->kundennummer ?: 'Nicht hinterlegt' }}</td></tr>
        <tr><th>Betreuung</th><td>{{ trim(($participation?->meta?->betreuer?->vorname ?? '').' '.($participation?->meta?->betreuer?->nachname ?? '')) ?: 'Nicht hinterlegt' }}</td></tr>
    </table>

    @forelse($sections as $section)
        <h2>{{ $section['heading'] ?? 'Abschnitt' }}</h2>
        <div class="section {{ !empty($section['claims']) ? 'claim-list' : '' }}">
            @if(!empty($section['claims']))
                @foreach($section['claims'] as $claim)
                    <div class="claim {{ ($claim['status'] ?? '') === 'insufficient_data' ? 'missing' : '' }}">
                        {{ $claim['text'] ?? '' }}
                        @if(!empty($claim['source_ids']))
                            <div class="sources">Interne Quellen: {{ implode(', ', $claim['source_ids']) }}</div>
                        @endif
                    </div>
                @endforeach
            @else
                {{ $section['value'] ?? '' }}
            @endif
        </div>
    @empty
        <h2>Individuelle Ausgangssituation</h2>
        <div class="section">{{ $luv->ausgangssituation }}</div>
        <h2>Schritte zur Zielerreichung</h2>
        <div class="section">{{ $luv->zielvereinbarung }}</div>
        @if($luv->qualifikationen)
            <h2>Qualifikationen</h2>
            <div class="section">{{ $luv->qualifikationen }}</div>
        @endif
    @endforelse

    @foreach(data_get($luv->payload, 'warnings', []) as $warning)
        <div class="warning">{{ $warning }}</div>
    @endforeach

    <table class="approval-table">
        <tr><th>Mit der teilnehmenden Person besprochen am</th><td>{{ $luv->discussed_on?->format('d.m.Y') ?: 'Noch nicht bestätigt' }}</td></tr>
        <tr><th>Einwilligung bestätigt</th><td>{{ $luv->consent_confirmed ? 'Ja' : 'Noch nicht bestätigt' }}</td></tr>
        <tr><th>Fachlich freigegeben</th><td>{{ $luv->approved_at ? $luv->approved_at->format('d.m.Y H:i').' Uhr' : 'Nein' }}</td></tr>
    </table>

    <div class="footer">
        KI-unterstützter Entwurf: {{ $luv->ai_report_run_id ? 'ja' : 'nein' }}. Eine fachliche Prüfung und Freigabe durch autorisiertes Personal ist erforderlich.
    </div>
</body>
</html>
