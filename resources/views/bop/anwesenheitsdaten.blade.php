<!doctype html>
<html lang="{{ auth()->check() ? auth()->user()->lang : 'de' }}" class="theme-{{ auth()->check() ? (auth()->user()->theme ?? 'air') : 'air' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Anwesenheitsdaten</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('img/logo/zbb-icon2.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/line-awesome.css') }}">
    <style>
        :root {
            --bg: #f7f8fa;
            --card: #ffffff;
            --primary: #172033;
            --secondary: #5d6676;
            --border: #d9dee5;
            --muted: #eef2f6;
            --headerBg: #ffffff;
            --sidebarBg: #102033;
            --zbb: #ff7a00;
            --zbb-hover: #df6900;
            --success: #18a545;
            --danger: #f5224f;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--primary);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
        }
        a { color: inherit; text-decoration: none; }
        .app-header {
            position: sticky;
            top: 0;
            z-index: 40;
            width: 100%;
            height: 80px;
            border-bottom: 1px solid var(--border);
            background: var(--headerBg);
            box-shadow: 0 1px 3px rgba(15, 23, 42, .08);
        }
        .app-header-inner {
            height: 100%;
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        .app-header-left,
        .app-header-right,
        .main-nav {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .app-header-left { gap: 18px; }
        .app-header-right { gap: 4px; }
        .menu-toggle,
        .icon-button {
            border: 0;
            background: transparent;
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            cursor: pointer;
            border-radius: 6px;
            padding: 0;
        }
        .menu-toggle:hover,
        .icon-button:hover { color: var(--zbb); background: transparent; }
        .brand {
            display: inline-flex;
            align-items: center;
            height: 36px;
        }
        .brand img { height: 36px; width: auto; display: block; }
        .main-nav a {
            height: 80px;
            display: inline-flex;
            align-items: center;
            border-bottom: 3px solid transparent;
            color: var(--primary);
            font-size: 17px;
            padding: 0 8px;
        }
        .main-nav a.active,
        .main-nav a:hover {
            color: var(--zbb);
            border-bottom-color: var(--zbb);
        }
        .app-body {
            display: flex;
            min-height: calc(100vh - 80px);
        }
        .app-sidebar {
            width: 268px;
            flex: 0 0 268px;
            background: var(--sidebarBg);
            color: #fff;
            transition: width .2s ease, transform .2s ease;
        }
        .app-sidebar.collapsed {
            width: 76px;
            flex-basis: 76px;
        }
        .sidebar-inner {
            position: sticky;
            top: 80px;
            max-height: calc(100vh - 80px);
            overflow-y: auto;
            padding: 18px 14px;
        }
        .sidebar-section {
            margin: 2px 10px 14px;
            color: rgba(255,255,255,.72);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 38px;
            padding: 8px 10px;
            border-radius: 4px;
            color: #fff;
            font-size: 14px;
            transition: background .16s ease, color .16s ease;
        }
        .sidebar-link i {
            width: 24px;
            text-align: center;
            font-size: 20px;
            color: inherit;
        }
        .sidebar-link:hover,
        .sidebar-link.active {
            background: color-mix(in srgb, var(--zbb) 22%, var(--sidebarBg));
            color: #fff;
        }
        .sidebar-sub {
            margin: 2px 0 8px 34px;
            display: grid;
            gap: 4px;
        }
        .sidebar-sub a {
            display: block;
            padding: 5px 8px;
            color: rgba(255,255,255,.72);
            border-radius: 4px;
        }
        .sidebar-sub a:hover { color: #fff; background: rgba(255,255,255,.08); }
        .app-sidebar.collapsed .sidebar-label,
        .app-sidebar.collapsed .sidebar-section,
        .app-sidebar.collapsed .sidebar-sub { display: none; }
        .app-content {
            min-width: 0;
            flex: 1;
        }
        .page { padding: 26px 17px 34px; }
        .topbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 26px;
        }
        .title-row { display: flex; align-items: center; gap: 8px; }
        h1 {
            margin: 0;
            font-size: 21px;
            font-weight: 500;
            letter-spacing: 0;
        }
        .title-icon { font-size: 27px; color: #111827; }
        .breadcrumb {
            margin-top: 8px;
            color: #222;
            font-size: 12px;
        }
        .summary {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 22px;
            font-weight: 700;
        }
        .actions {
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }
        .btn {
            border: 0;
            border-radius: 20px;
            min-height: 32px;
            padding: 0 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            font-size: 12px;
            line-height: 1;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-primary {
            background: var(--zbb);
            color: #fff;
        }
        .btn-primary:hover { background: var(--zbb-hover); }
        .btn-quiet {
            background: #fff;
            color: var(--primary);
            border: 1px solid var(--border);
        }
        .dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 40px;
            width: 250px;
            padding: 6px;
            border: 1px solid var(--border);
            background: var(--card);
            box-shadow: 0 18px 36px rgba(15, 23, 42, .14);
            z-index: 20;
        }
        .dropdown.open { display: block; }
        .dropdown button,
        .dropdown a {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 0;
            background: transparent;
            padding: 9px 10px;
            color: var(--primary);
            cursor: pointer;
            text-align: left;
            font: inherit;
        }
        .dropdown button:hover,
        .dropdown a:hover { background: var(--muted); }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 12px;
            align-items: center;
        }
        .filter {
            min-height: 32px;
            width: min(340px, 100%);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0 10px;
            background: #fff;
            color: var(--primary);
        }
        .table-shell {
            width: 100%;
            overflow: auto;
            border: 1px solid var(--border);
            background: var(--card);
        }
        table {
            width: 100%;
            min-width: 1500px;
            border-collapse: collapse;
        }
        th, td {
            border-right: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 0 7px;
            height: 38px;
            white-space: nowrap;
            vertical-align: middle;
        }
        th {
            position: sticky;
            top: 0;
            z-index: 5;
            height: 33px;
            background: #fbfcfd;
            color: #202631;
            font-weight: 500;
            text-align: center;
            user-select: none;
        }
        th.sortable { cursor: pointer; }
        th .sort { color: #a2a8b2; margin-left: 5px; font-size: 11px; }
        tbody tr:nth-child(even) { background: #fcfdff; }
        tbody tr:hover { background: #fff8f1; }
        .num, .center { text-align: center; }
        .id-link { color: #0076ff; }
        .gender-m { color: #10b94c; font-weight: 700; }
        .gender-w { color: #ff174b; font-weight: 700; }
        .status-cell {
            text-align: center;
            cursor: pointer;
            font-size: 15px;
            font-weight: 700;
        }
        .status-present { color: var(--success); }
        .status-absent { color: var(--danger); }
        .status-empty { color: #111827; font-weight: 500; }
        .row-actions {
            width: 44px;
            text-align: center;
            color: #717987;
            position: relative;
        }
        .row-menu-button {
            border: 0;
            background: transparent;
            color: inherit;
            cursor: pointer;
            font-size: 18px;
            width: 28px;
            height: 28px;
        }
        .empty-state {
            padding: 42px 20px;
            text-align: center;
            color: var(--secondary);
        }
        .toast {
            position: fixed;
            right: 18px;
            bottom: 18px;
            background: #172033;
            color: #fff;
            padding: 10px 14px;
            border-radius: 6px;
            opacity: 0;
            transform: translateY(10px);
            transition: .18s ease;
            z-index: 50;
        }
        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
        .feedback-modal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, .38);
            z-index: 80;
        }
        .feedback-modal.open { display: flex; }
        .feedback-dialog {
            width: min(360px, 100%);
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .26);
            padding: 22px;
            text-align: center;
        }
        .feedback-icon {
            width: 52px;
            height: 52px;
            margin: 0 auto 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            color: #fff;
            font-size: 30px;
            line-height: 1;
        }
        .feedback-modal.success .feedback-icon { background: var(--success); }
        .feedback-modal.error .feedback-icon { background: var(--danger); }
        .feedback-modal.info .feedback-icon { background: #64748b; }
        .feedback-title {
            margin: 0 0 6px;
            font-size: 18px;
            font-weight: 700;
            color: var(--primary);
        }
        .feedback-text {
            margin: 0 0 18px;
            color: var(--secondary);
            font-size: 13px;
        }
        .feedback-close {
            min-width: 86px;
            border: 0;
            border-radius: 20px;
            background: var(--zbb);
            color: #fff;
            padding: 9px 18px;
            cursor: pointer;
            font: inherit;
        }
        .feedback-close:hover { background: var(--zbb-hover); }
        .confirm-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 18px;
        }
        .confirm-cancel,
        .confirm-submit {
            min-width: 112px;
            border-radius: 20px;
            padding: 9px 18px;
            cursor: pointer;
            font: inherit;
        }
        .confirm-cancel {
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--primary);
        }
        .confirm-cancel:hover { background: var(--muted); }
        .confirm-submit {
            border: 0;
            background: var(--zbb);
            color: #fff;
        }
        .confirm-submit:hover { background: var(--zbb-hover); }
        @media (max-width: 760px) {
            .app-header-inner { padding: 0 14px; }
            .main-nav { display: none; }
            .app-sidebar {
                position: fixed;
                top: 80px;
                bottom: 0;
                left: 0;
                z-index: 35;
                transform: translateX(-100%);
            }
            .app-sidebar.open { transform: translateX(0); }
            .app-sidebar.collapsed {
                width: 268px;
                flex-basis: 268px;
            }
            .app-sidebar.collapsed .sidebar-label,
            .app-sidebar.collapsed .sidebar-section,
            .app-sidebar.collapsed .sidebar-sub { display: initial; }
            .page { padding: 18px 12px 28px; }
            .topbar { flex-direction: column; }
            .actions { width: 100%; }
            .btn { flex: 1; }
            .dropdown { left: 0; right: auto; width: min(280px, 100vw - 24px); }
        }
        @media print {
            .app-header,
            .app-sidebar { display: none; }
            .app-body { display: block; }
            body { background: #fff; }
            .actions, .toolbar { display: none; }
            .page { padding: 0; }
            .table-shell { border: 0; overflow: visible; }
            table { min-width: 0; font-size: 9px; }
            th, td { height: 24px; padding: 0 3px; }
        }
    </style>
</head>
<body>
    @php
        $exportContext = [
            'partnerId' => $partner->id,
            'schuljahr' => $schuljahr,
            'teil' => $teil,
            'tage' => $tage,
            'summenTage' => $summenTage,
            'students' => $schueler->map(function ($item) {
                return [
                    'id' => $item->id,
                    'nachname' => $item->person?->nachname,
                    'vorname' => $item->person?->vorname,
                    'geschlecht' => $item->person?->geschlecht,
                    'klasse' => $item->klasse,
                ];
            })->values(),
        ];
        $currentPerson = auth()->user()->person;
        $currentUserName = trim(($currentPerson?->vorname ?? '') . ' ' . ($currentPerson?->nachname ?? '')) ?: auth()->user()->username;
    @endphp

    <header class="app-header">
        <div class="app-header-inner">
            <div class="app-header-left">
                <button class="menu-toggle" type="button" id="sidebarToggle" aria-label="Sidebar umschalten">
                    <i class="las la-bars la-2x"></i>
                </button>
                <a class="brand" href="{{ route('dashboard') }}" aria-label="ZBB Dashboard">
                    <img src="{{ asset('img/logo/logoSimpliste.png') }}" alt="ZBB">
                </a>
            </div>

            <nav class="main-nav" aria-label="Hauptnavigation">
                <a href="{{ route('dashboard') }}" class="active">Dashboard</a>
                <a href="{{ route('organisation.index') }}">Organisation</a>
                <a href="{{ route('ressourcen.index') }}">Ressourcen</a>
                <a href="{{ route('finanzen.index') }}">Finanzen</a>
            </nav>

            <div class="app-header-right">
                <a class="icon-button" href="{{ route('apps.index') }}" title="Apps" aria-label="Apps">
                    <i class="las la-th-large la-lg"></i>
                </a>
                <button class="icon-button" type="button" title="Sprache" aria-label="Sprache">
                    <i class="las la-globe la-lg"></i>
                </button>
                <button class="icon-button" type="button" title="Darstellung" aria-label="Darstellung">
                    <i class="las la-adjust la-lg"></i>
                </button>
                <a class="icon-button" href="{{ route('user.profil', ['id' => auth()->user()->id]) }}" title="{{ $currentUserName }}" aria-label="Profil">
                    <i class="las la-user la-lg"></i>
                </a>
            </div>
        </div>
    </header>

    <div class="app-body">
        <aside class="app-sidebar" id="appSidebar">
            <div class="sidebar-inner">
                <div class="sidebar-section">Übersicht</div>
                <a class="sidebar-link" href="{{ route('dashboard') }}">
                    <i class="las la-dashboard"></i>
                    <span class="sidebar-label">Dashboard</span>
                </a>
                <a class="sidebar-link" href="{{ route('apps.index') }}">
                    <i class="las la-th-large"></i>
                    <span class="sidebar-label">Apps</span>
                </a>
                <div class="sidebar-sub">
                    <a href="{{ route('apps.calendar') }}">Kalender</a>
                    <a href="{{ route('apps.files') }}">Dateimanager</a>
                    <a href="{{ route('apps.tasks') }}">Taskmanager</a>
                </div>

                <div class="sidebar-section">Organisation</div>
                <a class="sidebar-link" href="{{ route('dashboard.partner.index') }}">
                    <i class="las la-building"></i>
                    <span class="sidebar-label">Partner</span>
                </a>
                <a class="sidebar-link" href="{{ route('projekt.index') }}">
                    <i class="las la-project-diagram"></i>
                    <span class="sidebar-label">Projekte</span>
                </a>
                <a class="sidebar-link" href="{{ route('bereich.index') }}">
                    <i class="las la-braille"></i>
                    <span class="sidebar-label">Bereiche</span>
                </a>
                <a class="sidebar-link active" href="{{ route('index-anpassung-anwesenheitsdaten', ['schulId' => $partner->id, 'schuljahr' => $schuljahr, 'teil' => $teil]) }}">
                    <i class="las la-table"></i>
                    <span class="sidebar-label">Anwesenheitsdaten</span>
                </a>

                <div class="sidebar-section">Teilnehmende</div>
                <a class="sidebar-link" href="{{ route('teilnehmer.index') }}">
                    <i class="las la-user-graduate"></i>
                    <span class="sidebar-label">Teilnehmer</span>
                </a>
                <a class="sidebar-link" href="{{ route('gruppe.index') }}">
                    <i class="las la-cookie"></i>
                    <span class="sidebar-label">Gruppe</span>
                </a>
            </div>
        </aside>

        <div class="app-content">
            <main class="page">
        <section class="topbar">
            <div>
                <div class="title-row">
                    <h1>Anwesenheitsdaten</h1>
                    <i class="las la-table title-icon"></i>
                </div>
                <div class="breadcrumb">
                    Dashboard&nbsp; / &nbsp;{{ $partner->name }}&nbsp; / &nbsp;Anwesenheitsdaten
                </div>
            </div>

            <div class="actions">
                <button class="btn btn-primary" type="button" id="generateBtn">
                    <i class="las la-sync"></i>
                    Generieren
                </button>
                <button class="btn btn-primary" type="button" id="exportToggle" aria-expanded="false">
                    <i class="las la-download"></i>
                    Exportieren
                    <i class="las la-angle-down"></i>
                </button>
                <div class="dropdown" id="exportMenu">
                    <form method="post" action="{{ route('export.anwesenheitsdaten.schule.excel', ['schulId' => $partner->id, 'schuljahr' => $schuljahr, 'teil' => $teil]) }}" id="xlsxExportForm">
                        @csrf
                        <input type="hidden" name="status_payload" id="statusPayload">
                        <button type="submit"><i class="las la-file-excel"></i> Anwesenheitsdaten Excel</button>
                    </form>
                    <button type="button" id="csvExport"><i class="las la-file-csv"></i> CSV herunterladen</button>
                    <button type="button" id="printExport"><i class="las la-print"></i> Drucken / PDF</button>
                    <a href="{{ route('export.teilnehmerliste.schule.excel', ['schuleId' => $partner->id, 'schuljahr' => $schuljahr, 'teil' => $teil]) }}"><i class="las la-list"></i> Teilnehmerliste</a>
                    <a href="{{ route('anwesenheitslisteVorBOTage', ['schuleId' => $partner->id, 'schuljahr' => $schuljahr, 'teil' => $teil]) }}"><i class="las la-clipboard-check"></i> BO Vorbereitung</a>
                    <a href="{{ route('export.anwesenheitsliste.rechnung', ['idSchule' => $partner->id, 'schuljahr' => $schuljahr, 'teil' => $teil]) }}"><i class="las la-file-invoice"></i> Anwesenheitsliste Rechnung</a>
                </div>
            </div>
        </section>

        <section class="summary" aria-live="polite">
            <span>Gesamtanzahl Anwesenheitstage: <span id="totalPresent">{{ $gesamtAnwesenheitstage }}</span></span>
            <span>Schüleranzahl PA: {{ $paAnzahl }}</span>
            <span>Schule: {{ $partner->name }}</span>
            <span>Schuljahr: {{ $schuljahr }}</span>
            <span>Teil: {{ $teil }}</span>
        </section>

        <section class="toolbar">
            <input class="filter" id="tableFilter" type="search" placeholder="Nach Name, Klasse oder ID filtern">
            <button class="btn btn-quiet" type="button" id="markVisiblePresent"><i class="las la-check"></i> Sichtbare ankreuzen</button>
            <button class="btn btn-quiet" type="button" id="markVisibleAbsent"><i class="las la-times"></i> Sichtbare abkreuzen</button>
            <button class="btn btn-quiet" type="button" id="clearVisible"><i class="las la-eraser"></i> Sichtbare leeren</button>
        </section>

        <section class="table-shell">
            @if($schueler->isEmpty())
                <div class="empty-state">Keine Teilnehmer gefunden.</div>
            @else
                <table id="attendanceTable">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="id">ID <span class="sort">⇅</span></th>
                            <th class="sortable" data-sort="nachname">Nachname <span class="sort">⇅</span></th>
                            <th class="sortable" data-sort="vorname">Vorname <span class="sort">⇅</span></th>
                            <th class="sortable" data-sort="geschlecht">W/M <span class="sort">⇅</span></th>
                            <th class="sortable" data-sort="klasse">Klasse <span class="sort">⇅</span></th>
                            @foreach($tage as $label)
                                <th>{{ $label }} <span class="sort">⇅</span></th>
                            @endforeach
                            <th class="sortable" data-sort="summe">Summe <span class="sort">⇅</span></th>
                            <th><i class="las la-cog"></i></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($schueler as $index => $item)
                            @php
                                $gender = $item->person?->geschlecht ?? '';
                                $search = trim(($index + 1) . ' ' . $item->person?->nachname . ' ' . $item->person?->vorname . ' ' . $gender . ' ' . $item->klasse);
                            @endphp
                            <tr data-student-id="{{ $item->id }}" data-search="{{ strtolower($search) }}">
                                <td class="num id-link" data-value="{{ $index + 1 }}">{{ $index + 1 }}</td>
                                <td data-value="{{ $item->person?->nachname }}">{{ $item->person?->nachname }}</td>
                                <td data-value="{{ $item->person?->vorname }}">{{ $item->person?->vorname }}</td>
                                <td class="center gender-{{ $gender }}" data-value="{{ $gender }}">{{ $gender }}</td>
                                <td class="center" data-value="{{ $item->klasse }}">{{ $item->klasse }}</td>
                                @foreach($tage as $key => $label)
                                    @php $defaultStatus = $key === 'bo1' ? 'absent' : 'present'; @endphp
                                    <td class="status-cell status-{{ $defaultStatus }}" data-day="{{ $key }}" data-status="{{ $defaultStatus }}" role="button" tabindex="0" aria-label="{{ $label }} Status ändern"></td>
                                @endforeach
                                <td class="center row-sum" data-value="0">0</td>
                                <td class="row-actions">
                                    <button type="button" class="row-menu-button" title="Zeile umschalten" aria-label="Zeile umschalten">
                                        <i class="las la-ellipsis-v"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
            </main>
        </div>
    </div>

    <div class="toast" id="toast"></div>
    <div class="feedback-modal" id="feedbackModal" role="dialog" aria-modal="true" aria-labelledby="feedbackTitle">
        <div class="feedback-dialog">
            <div class="feedback-icon" id="feedbackIcon"></div>
            <h2 class="feedback-title" id="feedbackTitle"></h2>
            <p class="feedback-text" id="feedbackText"></p>
            <button class="feedback-close" type="button" id="feedbackClose">OK</button>
        </div>
    </div>
    <div class="feedback-modal info" id="generateConfirmModal" role="dialog" aria-modal="true" aria-labelledby="generateConfirmTitle">
        <div class="feedback-dialog">
            <div class="feedback-icon">?</div>
            <h2 class="feedback-title" id="generateConfirmTitle">Generieren bestätigen</h2>
            <p class="feedback-text">Bist du sicher, dass du generieren möchtest? Bestehende Haken und Kreuze werden überschrieben.</p>
            <div class="confirm-actions">
                <button class="confirm-cancel" type="button" id="generateCancel">Abbrechen</button>
                <button class="confirm-submit" type="button" id="generateConfirm">Ja, generieren</button>
            </div>
        </div>
    </div>

    <script>
        const context = @json($exportContext);
        const days = Object.keys(context.tage);
        const sumDays = context.summenTage;
        const storageKey = `zbb:anwesenheitsdaten:${context.partnerId}:${context.schuljahr}:${context.teil}`;
        const statusSymbols = {
            present: { text: '✓', cls: 'status-present' },
            absent: { text: '✖', cls: 'status-absent' },
            empty: { text: '–', cls: 'status-empty' },
        };
        let sortState = { key: 'id', direction: 'asc' };
        let feedbackTimer = null;

        const table = document.getElementById('attendanceTable');
        const rows = () => table ? Array.from(table.querySelectorAll('tbody tr')) : [];
        const sidebar = document.getElementById('appSidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const exportToggle = document.getElementById('exportToggle');
        const exportMenu = document.getElementById('exportMenu');
        const toast = document.getElementById('toast');
        const feedbackModal = document.getElementById('feedbackModal');
        const feedbackIcon = document.getElementById('feedbackIcon');
        const feedbackTitle = document.getElementById('feedbackTitle');
        const feedbackText = document.getElementById('feedbackText');
        const feedbackClose = document.getElementById('feedbackClose');
        const generateConfirmModal = document.getElementById('generateConfirmModal');
        const generateCancel = document.getElementById('generateCancel');
        const generateConfirm = document.getElementById('generateConfirm');

        function showToast(message) {
            toast.textContent = message;
            toast.classList.add('show');
            window.setTimeout(() => toast.classList.remove('show'), 1900);
        }

        function statusMessage(status) {
            if (status === 'present') {
                return {
                    type: 'success',
                    icon: '\u2713',
                    title: 'Success',
                    text: 'Haken wurde gesetzt.',
                };
            }

            if (status === 'absent') {
                return {
                    type: 'error',
                    icon: '\u2716',
                    title: 'Error',
                    text: 'Kreuz wurde gesetzt.',
                };
            }

            return {
                type: 'info',
                icon: '\u2013',
                title: 'Info',
                text: 'Status wurde geleert.',
            };
        }

        function showFeedbackModal(status) {
            const message = statusMessage(status);
            feedbackModal.classList.remove('success', 'error', 'info');
            feedbackModal.classList.add(message.type, 'open');
            feedbackIcon.textContent = message.icon;
            feedbackTitle.textContent = message.title;
            feedbackText.textContent = message.text;

            window.clearTimeout(feedbackTimer);
            feedbackTimer = window.setTimeout(closeFeedbackModal, 1200);
        }

        function closeFeedbackModal() {
            feedbackModal.classList.remove('open');
            window.clearTimeout(feedbackTimer);
        }

        function openGenerateConfirmModal() {
            generateConfirmModal.classList.add('open');
        }

        function closeGenerateConfirmModal() {
            generateConfirmModal.classList.remove('open');
        }

        function setCellStatus(cell, status) {
            cell.dataset.status = status;
            cell.classList.remove('status-present', 'status-absent', 'status-empty');
            cell.classList.add(statusSymbols[status].cls);
            cell.textContent = status === 'present' ? '\u2713' : status === 'absent' ? '\u2716' : '\u2013';
        }

        function updateRowSum(row) {
            const sum = Array.from(row.querySelectorAll('.status-cell'))
                .filter(cell => sumDays.includes(cell.dataset.day) && cell.dataset.status === 'present')
                .length;
            const sumCell = row.querySelector('.row-sum');
            sumCell.textContent = sum;
            sumCell.dataset.value = sum;
        }

        function updateTotals() {
            const total = rows().reduce((count, row) => {
                return count + Array.from(row.querySelectorAll('.status-cell'))
                    .filter(cell => sumDays.includes(cell.dataset.day) && cell.dataset.status === 'present').length;
            }, 0);
            document.getElementById('totalPresent').textContent = total;
        }

        function payload() {
            return rows().reduce((data, row) => {
                const studentId = row.dataset.studentId;
                data[studentId] = {};
                row.querySelectorAll('.status-cell').forEach(cell => {
                    data[studentId][cell.dataset.day] = cell.dataset.status;
                });
                return data;
            }, {});
        }

        function saveState() {
            localStorage.setItem(storageKey, JSON.stringify(payload()));
            updateTotals();
        }

        function loadState() {
            let stored = {};
            try {
                stored = JSON.parse(localStorage.getItem(storageKey) || '{}');
            } catch (error) {
                stored = {};
            }

            rows().forEach(row => {
                const studentState = stored[row.dataset.studentId] || {};
                row.querySelectorAll('.status-cell').forEach(cell => {
                    const fallback = cell.dataset.status || 'empty';
                    setCellStatus(cell, studentState[cell.dataset.day] || fallback);
                });
                updateRowSum(row);
            });
            updateTotals();
        }

        function cycleStatus(cell) {
            const next = cell.dataset.status === 'present'
                ? 'absent'
                : cell.dataset.status === 'absent'
                    ? 'empty'
                    : 'present';
            setCellStatus(cell, next);
            updateRowSum(cell.closest('tr'));
            saveState();
            showFeedbackModal(next);
        }

        function setVisibleRows(status) {
            rows()
                .filter(row => row.style.display !== 'none')
                .forEach(row => {
                    row.querySelectorAll('.status-cell').forEach(cell => setCellStatus(cell, status));
                    updateRowSum(row);
                });
            saveState();
            showFeedbackModal(status);
        }

        function generateDefaults() {
            rows().forEach(row => {
                row.querySelectorAll('.status-cell').forEach(cell => {
                    setCellStatus(cell, cell.dataset.day === 'bo1' ? 'absent' : 'present');
                });
                updateRowSum(row);
            });
            saveState();
            showToast('Anwesenheitsdaten wurden generiert.');
            showFeedbackModal('present');
        }

        function sortTable(key) {
            const direction = sortState.key === key && sortState.direction === 'asc' ? 'desc' : 'asc';
            sortState = { key, direction };
            const indexMap = { id: 0, nachname: 1, vorname: 2, geschlecht: 3, klasse: 4, summe: days.length + 5 };
            const cellIndex = indexMap[key];
            const tbody = table.querySelector('tbody');

            rows().sort((a, b) => {
                const aValue = a.children[cellIndex].dataset.value || a.children[cellIndex].textContent.trim();
                const bValue = b.children[cellIndex].dataset.value || b.children[cellIndex].textContent.trim();
                const numeric = ['id', 'summe'].includes(key);
                const result = numeric
                    ? Number(aValue) - Number(bValue)
                    : aValue.localeCompare(bValue, 'de', { numeric: true, sensitivity: 'base' });
                return direction === 'asc' ? result : -result;
            }).forEach(row => tbody.appendChild(row));
        }

        function exportCsv() {
            const header = ['ID', 'Nachname', 'Vorname', 'W/M', 'Klasse', ...Object.values(context.tage), 'Summe'];
            const lines = [header];

            rows().forEach(row => {
                const values = [
                    row.children[0].textContent.trim(),
                    row.children[1].textContent.trim(),
                    row.children[2].textContent.trim(),
                    row.children[3].textContent.trim(),
                    row.children[4].textContent.trim(),
                ];
                row.querySelectorAll('.status-cell').forEach(cell => {
                    values.push(cell.dataset.status === 'present' ? 'x' : cell.dataset.status === 'absent' ? '-' : '');
                });
                values.push(row.querySelector('.row-sum').textContent.trim());
                lines.push(values);
            });

            const csv = lines.map(line => line.map(value => `"${String(value).replaceAll('"', '""')}"`).join(';')).join('\r\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `Anwesenheitsdaten_${context.partnerId}_${context.schuljahr}_Teil_${context.teil}.csv`;
            link.click();
            URL.revokeObjectURL(link.href);
        }

        if (table) {
            table.addEventListener('click', event => {
                const statusCell = event.target.closest('.status-cell');
                if (statusCell) {
                    cycleStatus(statusCell);
                    return;
                }

                const rowButton = event.target.closest('.row-menu-button');
                if (rowButton) {
                    const row = rowButton.closest('tr');
                    const allPresent = Array.from(row.querySelectorAll('.status-cell')).every(cell => cell.dataset.status === 'present');
                    const next = allPresent ? 'absent' : 'present';
                    row.querySelectorAll('.status-cell').forEach(cell => setCellStatus(cell, next));
                    updateRowSum(row);
                    saveState();
                    showFeedbackModal(next);
                }
            });

            table.addEventListener('keydown', event => {
                if (event.key === 'Enter' || event.key === ' ') {
                    const statusCell = event.target.closest('.status-cell');
                    if (statusCell) {
                        event.preventDefault();
                        cycleStatus(statusCell);
                    }
                }
            });

            table.querySelectorAll('th.sortable').forEach(th => {
                th.addEventListener('click', () => sortTable(th.dataset.sort));
            });
        }

        document.getElementById('tableFilter').addEventListener('input', event => {
            const value = event.target.value.trim().toLowerCase();
            rows().forEach(row => {
                row.style.display = row.dataset.search.includes(value) ? '' : 'none';
            });
        });

        document.getElementById('generateBtn').addEventListener('click', openGenerateConfirmModal);
        document.getElementById('markVisiblePresent').addEventListener('click', () => setVisibleRows('present'));
        document.getElementById('markVisibleAbsent').addEventListener('click', () => setVisibleRows('absent'));
        document.getElementById('clearVisible').addEventListener('click', () => setVisibleRows('empty'));
        document.getElementById('csvExport').addEventListener('click', exportCsv);
        document.getElementById('printExport').addEventListener('click', () => window.print());
        document.getElementById('xlsxExportForm').addEventListener('submit', () => {
            document.getElementById('statusPayload').value = JSON.stringify(payload());
        });

        exportToggle.addEventListener('click', () => {
            const open = exportMenu.classList.toggle('open');
            exportToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', event => {
            if (!event.target.closest('.actions')) {
                exportMenu.classList.remove('open');
                exportToggle.setAttribute('aria-expanded', 'false');
            }
        });

        feedbackClose.addEventListener('click', closeFeedbackModal);
        feedbackModal.addEventListener('click', event => {
            if (event.target === feedbackModal) {
                closeFeedbackModal();
            }
        });
        generateCancel.addEventListener('click', closeGenerateConfirmModal);
        generateConfirm.addEventListener('click', () => {
            closeGenerateConfirmModal();
            generateDefaults();
        });
        generateConfirmModal.addEventListener('click', event => {
            if (event.target === generateConfirmModal) {
                closeGenerateConfirmModal();
            }
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                closeFeedbackModal();
                closeGenerateConfirmModal();
            }
        });

        sidebarToggle.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 760px)').matches) {
                sidebar.classList.toggle('open');
                return;
            }

            sidebar.classList.toggle('collapsed');
        });

        loadState();
    </script>
</body>
</html>
