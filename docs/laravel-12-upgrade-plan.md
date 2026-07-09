# Laravel 12 Upgrade Plan

Stand: 2026-07-09

## Aktueller sicherer Stand

- `composer check` laeuft erfolgreich.
- `npm audit --audit-level=moderate` meldet 0 Sicherheitsluecken.
- `composer audit` meldet keine bekannten Sicherheitsluecken.
- `composer check:fresh-seed` prueft den kompletten Neuaufbau mit Seed in einer temporaeren SQLite-Datenbank.
- Composer-Patch-, Minor- und Laravel-12-Updates wurden eingespielt.
- Das Frontend baut ohne Vite-Chunk- und Browserdaten-Warnungen.
- Laravel 12 ist installiert und die Laravel-Version wurde mit `php artisan --version` geprueft.

## Was beim Upgrade passiert ist

Laravel 12 ist ein Major-Upgrade. Das bedeutet: mehrere Hauptpakete wechseln gleichzeitig ihre grosse Version. Dazu gehoeren:

- `laravel/framework` von 10.x auf 12.x
- `laravel/jetstream` von 3.x auf 5.x
- `laravel/sanctum` von 3.x auf 4.x
- `nunomaduro/collision` von 6.x auf 8.x
- `phpunit/phpunit` von 9.x auf 11.x

Das hat echte Code- und Test-Anpassungen ausgeloest. Der aktuelle Stand ist deshalb nicht nur installiert, sondern mit Tests, Build, Audits und Fresh-Seed-Check abgesichert.

## Vorbereitung, die bereits gemacht wurde

- Carbon-3-Risiko reduziert: `diffInDays()` wird an der relevanten Stelle explizit als Ganzzahl behandelt.
- Sanctum-Konfiguration vorbereitet: Laravel 10 nutzt weiter `VerifyCsrfToken`, Laravel 11/12 kann automatisch `ValidateCsrfToken` verwenden.
- Alte npm-Inertia-Pakete wurden entfernt, weil die App bereits `@inertiajs/vue3` nutzt.

## Verwendeter Upgrade-Befehl

```bash
composer update laravel/framework laravel/jetstream laravel/sanctum nunomaduro/collision phpunit/phpunit --with-all-dependencies
```

Dabei muessen vorher in `composer.json` diese Versionen gesetzt werden:

```json
"laravel/framework": "^12.0",
"laravel/jetstream": "^5.0",
"laravel/sanctum": "^4.0",
"nunomaduro/collision": "^8.0",
"phpunit/phpunit": "^11.0"
```

## Pflichtpruefungen nach Updates

Vor jedem Update zuerst die sichere Update-Checkliste beachten:

```text
docs/update-checklist.md
```

```bash
composer check:update
```

Alternativ einzeln:

```bash
composer check
composer check:fresh-seed
composer audit
npm audit --audit-level=moderate
php artisan route:list --except-vendor
```

Erst wenn diese Befehle sauber laufen, gilt ein Update als stabil genug fuer den echten Server.

## Wiederkehrende Nacharbeiten

- Wenn neue Migrationen entstehen, `composer check:fresh-seed` ausfuehren.
- Wenn neue Routen oder Berechtigungen entstehen, `composer check` ausfuehren, damit der Permission-Katalog sie prueft.
- Wenn Frontend-Abhaengigkeiten geaendert werden, `npm audit --audit-level=moderate` und `composer check` ausfuehren.
