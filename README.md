# Projektbeschreibung

Dieses Projekt ist eine Webanwendung, die mit den folgenden Technologien entwickelt wurde:

## Programmiersprachen
- **PHP**: Für die serverseitige Logik und Backend-Entwicklung.
- **JavaScript**: Für die clientseitige Interaktivität und dynamische Inhalte.
- **HTML & CSS**: Für das Markup und das Styling der Benutzeroberfläche.

## Verwendete Tools
- **XAMPP**: Lokale Entwicklungsumgebung mit Apache, MySQL und PHP.
- **phpMyAdmin**: Verwaltung der MySQL-Datenbank.
- **Visual Studio Code**: Quelltexteditor für die Entwicklung.
- **Composer**: Paketverwaltung für PHP-Bibliotheken.

## Verwendete Frameworks und Bibliotheken
- **Laravel**: Modernes PHP-Framework für die Entwicklung von Webanwendungen. Es bietet eine klare Struktur, viele eingebaute Funktionen und eine große Community.

### Vorteile von Laravel
- **Klar strukturierter Code**: MVC-Architektur sorgt für eine saubere Trennung von Logik und Darstellung.
- **Viele eingebaute Funktionen**: Authentifizierung, Routing, Caching, E-Mail-Versand und mehr sind direkt integriert.
- **Große Community & Ökosystem**: Umfangreiche Dokumentation, viele Pakete und schnelle Hilfe bei Problemen.
- **Hohe Sicherheit**: Schutz vor gängigen Sicherheitslücken wie SQL-Injection, CSRF und XSS.
- **Einfache Erweiterbarkeit**: Durch Pakete und Middleware kann die Anwendung flexibel erweitert werden.
- **Moderne Entwicklungstools**: Unterstützung für Migrations, Seeders, Testing und Task Scheduling.

- **Vue.js**: Fortschrittliches JavaScript-Framework für die Erstellung interaktiver Benutzeroberflächen.
- **Inertia.js**: Verbindet Laravel und Vue.js, um Single-Page-Anwendungen ohne API zu ermöglichen.
- **Tailwind CSS**: Utility-First CSS-Framework für ein modernes und flexibles Design.

## Authentifizierungsmethoden
Die Anwendung nutzt **Laravel Jetstream** und **Laravel Fortify** für die Authentifizierung:
- **Laravel Jetstream**: Bietet eine vollständige Authentifizierungslösung mit Login, Registrierung, Passwort-Reset, E-Mail-Verifizierung und optional Zwei-Faktor-Authentifizierung.
- **Laravel Fortify**: Stellt die Backend-Logik für Authentifizierungsprozesse bereit, wie Login, Registrierung, Passwort-Reset und mehr.

Die Authentifizierung basiert auf **Sessions**. Nach erfolgreicher Anmeldung wird eine Session gestartet, um den Benutzerstatus zu speichern und zu überprüfen.

## Installation auf anderen Rechnern

Um das Programm auf einem anderen Rechner zu installieren, folge diesen Schritten:

1. **Voraussetzungen installieren**: Stelle sicher, dass PHP, Composer, MySQL und ein Webserver (z.B. Apache über XAMPP) installiert sind.
2. **Projektdateien kopieren**: Kopiere den gesamten Projektordner auf den Zielrechner.
3. **Abhängigkeiten installieren**: Öffne ein Terminal im Projektordner und führe `composer install` aus, um alle PHP-Abhängigkeiten zu installieren.
4. **Umgebungsdatei konfigurieren**: Passe die Datei `.env` an (z.B. Datenbankzugangsdaten).
5. **Datenbank einrichten**: Erstelle eine neue Datenbank und führe `php artisan migrate` aus, um die Tabellen zu erstellen.
6. **Frontend-Abhängigkeiten (optional)**: Falls verwendet, installiere mit `npm install` und baue die Assets mit `npm run dev`.
7. **Webserver starten**: Starte den Webserver (z.B. über XAMPP) und rufe die Anwendung im Browser auf.

**php voraussetzungen (php.ini) für phpoffice/phpspreadsheet**

extension=zip
extension=mbstring
extension=gd
