# Sichere Update-Checkliste

Diese Checkliste ist fuer normale Updates gedacht: Composer-Pakete, npm-Pakete, Laravel-Updates, neue Migrationen oder groessere Code-Aenderungen.

## 1. Vor dem Update

### Echte Datenbank sichern

Bevor ein Befehl Daten veraendern kann, muss ein Backup existieren.

Bei XAMPP oder phpMyAdmin:

1. phpMyAdmin oeffnen.
2. Datenbank auswaehlen, zum Beispiel `matrix_db`.
3. Tab `Exportieren` oeffnen.
4. Methode `Schnell` oder `Angepasst` waehlen.
5. Format `SQL` waehlen.
6. Datei herunterladen und sicher aufbewahren.

Auf einem Server mit `mysqldump`:

```bash
mkdir -p storage/backups
mysqldump -u DB_USERNAME -p DB_DATABASE > storage/backups/matrix_db_YYYY-MM-DD_HHMM.sql
```

`DB_USERNAME` und `DB_DATABASE` stehen in `.env`. Das Passwort nicht direkt in den Befehl schreiben, damit es nicht in der Terminal-Historie landet.

### Arbeitsstand pruefen

```bash
git status
composer validate --strict
composer check
```

Wenn hier Fehler erscheinen, erst diese Fehler loesen und danach updaten.

## 2. Update ausfuehren

PHP-Pakete:

```bash
composer update
```

JavaScript-Pakete:

```bash
npm update
```

Wenn Laravel oder wichtige Pakete aktualisiert werden, danach immer alle Pflichtpruefungen ausfuehren.

## 3. Nach dem Update pruefen

Einfachster Gesamtcheck:

```bash
composer check:update
```

Dieser Befehl braucht Internetzugang fuer `composer audit` und `npm audit`.

Wenn das Projekt in GitHub liegt, laeuft derselbe Check auch automatisch ueber `.github/workflows/ci.yml` bei `push` und `pull_request`.

Wenn du die Schritte einzeln ausfuehren willst:

```bash
composer validate --strict
composer check
composer check:fresh-seed
composer audit
npm audit --audit-level=moderate
php artisan route:list --except-vendor
php artisan optimize
php artisan optimize:clear
```

Diese Befehle bedeuten:

- `composer check:update`: kompletter Update-Check in einem Befehl.
- `composer check`: Backend-Tests plus Frontend-Build.
- `composer check:fresh-seed`: kompletter Datenbank-Neuaufbau in einer temporaeren Testdatenbank.
- `composer audit`: bekannte Sicherheitsluecken in PHP-Paketen.
- `npm audit --audit-level=moderate`: bekannte Sicherheitsluecken in JavaScript-Paketen.
- `php artisan route:list --except-vendor`: prueft, ob Laravel alle App-Routen laden kann.
- `php artisan optimize`: prueft, ob Laravel Caches sauber erzeugen kann.
- `php artisan optimize:clear`: entfernt diese Caches danach wieder fuer die lokale Entwicklung.

## 4. Wichtige Datenbank-Regel

Diese Befehle loeschen Daten:

```bash
php artisan migrate:fresh
php artisan migrate:fresh --seed
php artisan migrate:refresh
php artisan migrate:refresh --seed
```

Nur verwenden, wenn klar ist, dass die aktuelle Datenbank neu aufgebaut werden darf.

Fuer bestehende echte Daten normalerweise diesen Befehl nutzen:

```bash
php artisan migrate
```

`php artisan migrate` fuegt neue Tabellen oder Spalten hinzu, ohne die vorhandenen Tabellen komplett zu loeschen.

## 5. Wenn nach dem Update Login nicht geht

Zuerst pruefen, ob Benutzer vorhanden sind:

```bash
php artisan db:show --counts
```

Wenn `users` den Wert `0` hat, gibt es in der aktuell verbundenen Datenbank keinen Benutzer. Dann ist das kein Passwortproblem, sondern ein Datenbankproblem.

Moegliche Loesungen:

- Backup wieder einspielen, wenn alte Daten wichtig sind.
- Bei einer komplett neuen leeren Datenbank `php artisan migrate:fresh --seed` nutzen.

Nach einem Seed existiert der Admin-Benutzer aus dem Seeder. Das Standardpasswort danach sofort aendern.
