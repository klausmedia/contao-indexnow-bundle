# IndexNow-Bundle für Contao

[English](README.md)

Meldet neue, geänderte und entfernte Seiten, Nachrichten, Events und FAQs automatisch an [IndexNow](https://www.indexnow.org/). Die Meldung geht an Bing, Yandex, Seznam, Naver und Yep. Diese Suchmaschinen teilen die Meldungen untereinander, deshalb reicht ein einziger Endpunkt. Google nimmt an IndexNow nicht teil.

## Voraussetzungen

- Contao 6.0 oder neuer (6.x)
- PHP 8.4 oder neuer
- Für Nachrichten, Events und FAQs: das jeweilige Contao-Bundle (optional)
- Ein Contao-Cronjob, der regelmäßig läuft (siehe [Cronjob](#cronjob))

## Installation

Im Contao Manager nach `klausmedia/contao-indexnow-bundle` suchen, das Paket installieren und die Datenbank aktualisieren.

Oder auf der Konsole:

```bash
composer require klausmedia/contao-indexnow-bundle
vendor/bin/contao-console contao:migrate
```

Die Aktualisierung der Datenbank legt Folgendes an:

| Name | Zweck |
| --- | --- |
| `tl_page.indexnowEnable`, `tl_page.indexnowKey`, `tl_page.indexnowAutoSubmit` | Einstellungen im Startpunkt |
| `tl_km_indexnow_queue` | Warteschlange der zu meldenden URLs |
| `tl_km_indexnow_state` | Letzte Läufe der Cronjobs |

## Einrichtung

1. Im Backend den Startpunkt der Website öffnen. Im Bereich **IndexNow** die Option **Änderungen an IndexNow melden** aktivieren.
2. Das Feld **IndexNow-Schlüssel** leer lassen und speichern: Das Bundle erzeugt dann einen Schlüssel mit 32 Zeichen. Ein eigener Schlüssel muss 8–128 Zeichen lang sein und darf nur `a–z`, `A–Z`, `0–9` und `-` enthalten.
3. Im Startpunkt eine **Domain** eintragen. Ohne Domain kann der Cronjob auf der Konsole keine gültigen URLs erzeugen.
4. Prüfen, dass `https://<domain>/<schlüssel>.txt` im Browser den Schlüssel anzeigt.
5. Optional: alle bestehenden URLs einmal melden, unter **System → IndexNow** mit **Alle URLs einreihen** oder auf der Konsole mit `contao:indexnow:submit --all`. Nötig ist das nur bei neuen oder schlecht indexierten Websites. Seiten, die Bing schon kennt, findet es auch so.

Bei mehreren Websites erledigst du diese Schritte für jeden Startpunkt, der melden soll. Jede Website hat einen eigenen Schlüssel. Haben mehrere Startpunkte dieselbe Domain, z. B. einer pro Sprache, genügt einer mit IndexNow: Gemeldet werden dann alle URLs dieser Domain.

**Websites ohne HTTPS** werden genauso gemeldet: IndexNow nimmt http- und https-URLs an. Ob die URLs mit `http://` oder `https://` beginnen, legt das Feld **Protokoll** im Startpunkt fest. Die Schlüsseldatei ist unter beiden Protokollen erreichbar. Das Protokoll im Startpunkt muss zur Website passen. Leitet der Server http auf https um und steht der Startpunkt noch auf http, meldet das Bundle http-URLs, und die Suchmaschine findet die Schlüsseldatei womöglich nicht.

**Schlüssel ändern:** Der Schlüssel lässt sich jederzeit im Startpunkt ändern. Leert man das Feld und speichert, entsteht ein neuer. Die neue Schlüsseldatei ist sofort erreichbar, und wartende URLs werden mit dem neuen Schlüssel gemeldet. Bis die Suchmaschine ihn geprüft hat, kann die Antwort HTTP 202 lauten. Meldungen, die kurz vor dem Wechsel mit HTTP 202 angenommen wurden, können verloren gehen. Ein Wechsel ist nur nötig, wenn der Schlüssel ersetzt werden soll.

## Was gemeldet wird

### Gemeldete Einträge

Gemeldet werden Seiten, Nachrichten, Events und FAQs mit eigener Detailseite.

Nicht gemeldet werden:

- geschützte Inhalte, auch wenn der Schutz von einer Elternseite, vom Archiv oder von der Leserseite kommt
- Inhalte mit `noindex` in den Robots-Angaben, auch in denen der Leserseite
- Nachrichten, Events und FAQs, deren Leserseite nicht veröffentlicht ist
- Seiten mit „In Sitemap anzeigen: nie“
- Seitentypen ohne eigenen Inhalt: Startpunkt, Ordner, Weiterleitungen, Logout, Fehlerseiten und Newsfeeds
- Nachrichten und Events, die nur auf eine Seite, einen Artikel oder eine externe URL verweisen
- Websites, deren Startpunkt IndexNow nicht aktiviert hat oder nicht veröffentlicht ist. Ein Startpunkt ohne Domain meldet nur für Domains, die keinen eigenen Startpunkt haben.

### Auslöser

| Aktion im Backend | Gemeldete URLs |
| --- | --- |
| Speichern eines veröffentlichten Eintrags | URL des Eintrags |
| Veröffentlichen und Zurückziehen, auch mit dem Augen-Symbol | URL des Eintrags. Beim Zurückziehen erfährt die Suchmaschine, dass er weg ist. |
| Neuer Alias | Alte und neue URL |
| Neuer Alias einer Leserseite | Zusätzlich alte und neue URLs aller Nachrichten, Events und FAQs, die diese Seite als Leserseite nutzen |
| Löschen | URL des Eintrags. Bei Seiten zusätzlich alle Unterseiten, bei Archiven, Kalendern und FAQ-Kategorien alle Einträge. |
| Verschieben, auch mehrere auf einmal | URLs vor und nach dem Verschieben. Bei Seiten zusätzlich die Unterseiten. |
| Kopieren | Die Kopie, sobald sie sichtbar ist. Kopierte Seiten, Artikel und Nachrichten legt Contao unveröffentlicht an. |
| Wiederherstellen aus dem Papierkorb | Alle wiederhergestellten Einträge, auch die mitgelöschten |
| Ältere Version wiederherstellen | URL vor und nach dem Wiederherstellen |
| Start- oder Endzeit erreicht („Anzeigen ab“ / „Anzeigen bis“) | URL des Eintrags, beim nächsten Cronjob-Lauf |

Ändert sich ein **Artikel** oder ein **Inhaltselement**, wird die Seite, Nachricht oder das Event gemeldet, zu dem es gehört. Das gilt für alle Aktionen aus der Tabelle, auch bei verschachtelten Elementen. Voraussetzung ist, dass die Seite, Nachricht oder das Event selbst veröffentlicht ist.

Ändert sich der Alias einer Seite, ändern sich die URLs ihrer Unterseiten nicht. Contao speichert den vollständigen Pfad im Alias jeder Unterseite. Die Unterseiten werden deshalb nicht gemeldet.

## Backend: System → IndexNow

Die Liste zeigt die Warteschlange: jede URL, die noch gemeldet werden muss, mit dem Zeitpunkt der Änderung. Du kannst sie nach Website filtern, nach URL durchsuchen und einzelne oder mehrere Einträge löschen.

| Schaltfläche | Wirkung |
| --- | --- |
| **Jetzt senden** | Schickt die ganze Warteschlange sofort an IndexNow, auch die von Websites ohne automatisches Senden. Das Ergebnis erscheint als Meldung. |
| **Alle URLs einreihen** | Liest `/sitemap.xml` jeder Website mit IndexNow und stellt alle URLs in die Warteschlange. Für einen Startpunkt ohne Domain wird die Domain des Backends verwendet. |

Benutzer ohne Administratorrechte brauchen in ihrer Benutzergruppe das Modul **IndexNow**.

### Automatisch senden

Im Startpunkt ist **Automatisch senden** standardmäßig aktiviert. Dann schickt der Cronjob die Warteschlange alle 5 Minuten. Ist die Option aus, sammelt das Bundle die URLs weiter, sendet sie aber erst mit **Jetzt senden**. Das ist sinnvoll für Staging-Seiten, zum Testen und auf Servern ohne Cronjob.

### Wartungsmodus

Ist im Startpunkt der **Wartungsmodus** aktiv, sammelt das Bundle die URLs weiter, sendet sie aber nicht, auch nicht mit **Jetzt senden** oder auf der Konsole. Suchmaschinen würden sonst nur die Wartungsseite (HTTP 503) erhalten. Nach dem Ende des Wartungsmodus sendet der nächste Cronjob-Lauf die URLs, ohne **Automatisch senden** genügt ein Klick auf **Jetzt senden**. Die Schlüsseldatei bleibt erreichbar.

Im systemweiten Wartungsmodus von Contao läuft der Cronjob nicht, und die URLs bleiben in der Warteschlange. Der Konsolenbefehl sollte dann nicht verwendet werden, weil die Schlüsseldatei nicht erreichbar ist.

### Hinweise in der Liste

| Hinweis | Bedeutung |
| --- | --- |
| Automatisches Senden ist ausgeschaltet für … | Diese Websites melden nur mit **Jetzt senden**. |
| Der Wartungsmodus ist aktiv für … | Diese Websites melden erst nach dem Ende des Wartungsmodus. |
| Der Cronjob ist noch nicht gelaufen | Direkt nach der Installation normal. Bleibt der Hinweis, läuft auf diesem Server kein Cronjob. |
| Der Cronjob ist seit … nicht mehr gelaufen | Der letzte Lauf liegt mehr als eine Stunde zurück. |

Die beiden Cronjob-Hinweise erscheinen nur, solange mindestens eine Website automatisch senden soll. Wer ohne Cronjob arbeitet, schaltet **Automatisch senden** aus, dann verschwinden sie.

## Ablauf

1. Eine Aktion im Backend stellt die URL in die Warteschlange (`tl_km_indexnow_queue`). Mehrfache Änderungen derselben URL ergeben einen einzigen Eintrag.
2. Der Cronjob schickt die Warteschlange alle 5 Minuten an `https://api.indexnow.org/indexnow`, außer bei Websites mit ausgeschaltetem **Automatisch senden**. Er bündelt die URLs pro Website, höchstens 10.000 pro Anfrage.
3. Die Antwort entscheidet, was mit den URLs passiert:

| Antwort | Folge |
| --- | --- |
| 200, 202 | Gemeldet, die URLs werden aus der Warteschlange entfernt |
| 429, 5xx, keine Verbindung | Neuer Versuch beim nächsten Lauf |
| 400, 403, 422 | Abgelehnt, die URLs werden entfernt und es gibt einen Fehler im Systemlog |

Alle Meldungen stehen auf Englisch im Contao-Systemlog und beginnen mit „IndexNow:“.

## Cronjob

Das Bundle bringt zwei Contao-Cronjobs mit, die alle 5 Minuten laufen:

- `SubmitQueueCron` schickt die Warteschlange und merkt sich den Zeitpunkt, damit das Backend erkennt, ob er läuft.
- `ScheduledContentCron` stellt Einträge in die Warteschlange, deren Start- oder Endzeit seit dem letzten Lauf erreicht wurde. Beim allerersten Lauf schaut er nur eine Stunde zurück.

Damit beide zuverlässig laufen, sollte der Server den Contao-Cronjob jede Minute aufrufen. Beispiel:

```bash
* * * * * /usr/bin/php /pfad/zum/projekt/vendor/bin/contao-console contao:cron
```

Die Pfade zu PHP und zur Contao-Installation müssen an den eigenen Server angepasst werden. PHP muss dabei in Version 8.4 oder neuer aufgerufen werden.

Ohne Server-Cronjob läuft der Contao-Cron nur bei Besuchen im Frontend. Meldungen können sich dann verzögern.

### Hosting ohne Cronjob

Das Bundle funktioniert auch ohne Cronjob. Im Startpunkt **Automatisch senden** ausschalten und die gesammelten URLs von Zeit zu Zeit unter **System → IndexNow** mit **Jetzt senden** melden. Wie lange eine URL schon wartet, steht in der Liste.

Ohne Cronjob fehlen zwei Dinge:

- Erreichte Start- und Endzeiten werden nicht erkannt. Der Eintrag wird erst gemeldet, wenn er wieder gespeichert wird.
- Es gibt keinen automatischen Versand. Darauf weist die Liste im Backend hin, siehe [Hinweise in der Liste](#hinweise-in-der-liste).

## Konsolenbefehl

```bash
vendor/bin/contao-console contao:indexnow:submit [--all] [--host=<domain>]
```

| Option | Wirkung |
| --- | --- |
| *(ohne)* | Schickt die Warteschlange sofort, auch die von Websites ohne automatisches Senden. Entspricht **Jetzt senden** im Backend. |
| `--all` | Entspricht **Alle URLs einreihen** mit anschließendem Senden: liest vorher `/sitemap.xml` jeder Website mit IndexNow und stellt alle URLs in die Warteschlange. Ein Sitemap-Index wird eine Ebene tief verfolgt. |
| `--host` | Zusammen mit `--all`: nur diese Domain. Nötig für einen Startpunkt ohne eingetragene Domain. |

Der Befehl endet mit einem Fehlercode, wenn IndexNow URLs abgelehnt hat.

## Prüfen, ob es funktioniert

1. `https://<domain>/<schlüssel>.txt` liefert den Schlüssel als Text.
2. Nach dem Speichern einer veröffentlichten Nachricht steht ihre URL unter **System → IndexNow**.
3. Nach dem nächsten Cronjob-Lauf steht im Systemlog: „IndexNow: … URL(s) of … reported (HTTP 200)“ bzw. „(HTTP 202)“.
4. Die [Bing Webmaster Tools](https://www.bing.com/webmasters) zeigen unter **IndexNow** die gemeldeten URLs, oft schon am selben Tag. Voraussetzung ist, dass die Website dort bestätigt ist.

## Fehlersuche

| Problem | Ursache und Lösung |
| --- | --- |
| Schlüsseldatei liefert 404 | Prüfen: IndexNow im Startpunkt aktiviert, Startpunkt veröffentlicht, Schlüssel gespeichert, Domain stimmt. Groß- und Kleinschreibung beachten. |
| HTTP 403 im Systemlog | Der Schlüssel passt nicht zur Schlüsseldatei, oder die Datei ist von außen nicht erreichbar, z. B. wegen eines Verzeichnisschutzes. Auch möglich: Das **Protokoll** im Startpunkt passt nicht zur Website. |
| HTTP 422 im Systemlog | Die URLs gehören nicht zur Domain des Schlüssels. Die Domain im Startpunkt prüfen. |
| Zeitgesteuerte Inhalte werden nicht gemeldet | Der Contao-Cronjob läuft nicht regelmäßig, oder der Startpunkt hat keine Domain. |
| Warteschlange leert sich nicht | Der Cronjob läuft nicht, oder **Automatisch senden** ist im Startpunkt aus. Die Hinweise in der Liste beachten und zum Test **Jetzt senden** klicken. |

## Einschränkungen

- Die Route `/{schlüssel}.txt` gilt für jede Adresse dieser Form mit 8–128 Zeichen und antwortet mit 404, wenn kein Schlüssel passt. Echte Dateien in `public/` haben Vorrang. Eine Contao-Seite mit der URL-Endung `.txt` würde dagegen abgefangen.
- Wird „Anzeigen bis“ direkt auf einen vergangenen Zeitpunkt gesetzt, wird das Entfernen womöglich nicht gemeldet. Den Eintrag stattdessen zurückziehen.
- Teilen sich mehrere Startpunkte eine Domain, entscheidet über den Wartungsmodus der Startpunkt, dessen Schlüssel verwendet wird.
- Wird ein unveröffentlichter Artikel samt Inhalt kopiert, meldet das Bundle die Seite einmal unnötig. Das schadet nicht.
- Schützt man eine Elternseite nachträglich, werden ihre Unterseiten nicht als entfernt gemeldet.
- Hat ein Archiv sehr viele Einträge, dauert das Speichern seiner Leserseite nach einer Alias-Änderung länger, weil jede URL einzeln erzeugt wird.
- Termine werden mit bis zu etwa 10 Minuten Verzögerung gemeldet, weil der Cronjob alle 5 Minuten läuft.

## Fehler melden

Bitte als [GitHub Issue](https://github.com/klausmedia/contao-indexnow-bundle/issues).

## Lizenz

[MIT](LICENSE) © Benny Klaus

Die Software wird kostenlos und ohne Gewährleistung bereitgestellt, die Einzelheiten regelt die [Lizenz](LICENSE). Vor Installation und Aktualisierung empfiehlt sich eine Sicherung von Dateien und Datenbank.
