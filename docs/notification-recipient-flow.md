# Notification recipient flow

Stand: 2026-07-09

## Grundregel

Controller entscheiden nicht mehr direkt, welche Benutzer eine fachliche Benachrichtigung bekommen.
Sie melden nur das Ereignis. Die zentrale Empfaengerlogik lebt in:

`app/Services/NotificationRecipientService.php`

Admins koennen die Regeln in der Anwendung unter
`/einstellung/benachrichtigungen` bearbeiten.

Die Notification-Klassen beschreiben weiterhin den Inhalt:

- Nachricht
- Link
- fachlicher Typ
- Status oder Zielobjekt

## Aktuelle Matrix

| Ereignis | Empfaengerregel | Kanal |
| --- | --- | --- |
| `materialanforderung.eingereicht` | Benutzer mit `materialanforderung.sachlische_freigabe.index` im Projektkontext | database |
| `materialanforderung.sachlich_genehmigt` | Benutzer mit `materialanforderung.kaufmännische_freigabe.update` | database |
| `materialanforderung.kaufmaennisch_genehmigt` | Benutzer mit `materialanforderung.bestellwesen.update` | database |
| `materialanforderung.zur_ueberarbeitung` | Ersteller der Materialanforderung | database |
| `materialanforderung.stornieren` | Ersteller der Materialanforderung | database |
| `materialanforderung.bestellt` | Ersteller der Materialanforderung | database |
| `materialanforderung.geliefert` | Ersteller der Materialanforderung | database |
| `materialanforderung.teilweise_geliefert` | Ersteller der Materialanforderung | database |
| `klassenbuch.woche.zur_pruefung` | Abteilungsassistenz und Abteilungsleitung der Projektabteilung, sonst Rollen-Fallback | database |

Bei Rollen- und Permission-Empfaengern wird der ausloesende Benutzer ausgeschlossen.
Bei Rueckmeldungen an den Ersteller wird der Ersteller bewusst als Ziel verwendet.

## Admin-Regeln

Die Regeln werden in `notification_rules` gespeichert.

Wichtige Felder:

- `event_key`: fachliches Ereignis, zum Beispiel `materialanforderung.eingereicht`
- `target_type`: Empfaengerart, zum Beispiel `permission`, `role`, `creator`
- `target_value`: Permission- oder Rollenname, falls erforderlich
- `scope`: optionaler Einschraenkungsbereich, aktuell `current_project`
- `active`: deaktivierte Regeln versenden keine Benachrichtigung
- `exclude_actor`: ausloesender Benutzer wird ausgeschlossen

Wenn die Tabelle noch nicht existiert oder fuer ein Ereignis keine Regeln vorhanden sind,
nutzt die Anwendung den bisherigen Code-Fallback.

## Ausbaupfad

1. Weitere Fachmodule an `NotificationRecipientService` anschliessen.
2. Queue aktivieren und Notifications bei Bedarf `ShouldQueue` implementieren lassen.
3. Optional `broadcast` ergaenzen, wenn die Glocke in Echtzeit aktualisiert werden soll.
