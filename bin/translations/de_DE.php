<?php
/**
 * German (de_DE) translation catalogue. THE SOURCE LANGUAGE IS ENGLISH.
 *
 * Address form: informal "du", matching the WordPress core de_DE translation.
 * WordPress keeps the formal variant in a separate locale (de_DE_formal); if it
 * is ever needed, add a second file here.
 *
 * @package no404
 */

return array(
	'Settings' => 'Einstellungen',
	'no404 – Auto 404 Redirect' => 'no404 – Automatische 404-Weiterleitung',
	'no404' => 'no404',
	'Connection' => 'Verbindung',
	'Behaviour' => 'Verhalten',
	'no404 address' => 'no404-Adresse',
	'API key' => 'API-Schlüssel',
	'Redirecting' => 'Weiterleitung',
	'Permanent redirects' => 'Dauerhafte Weiterleitungen',
	'Cache lifetime' => 'Cache-Dauer',
	'Timeout' => 'Zeitlimit',
	'Excluded paths' => 'Ausgeschlossene Pfade',
	'Get your API key from the site settings page in your no404 dashboard. The key is used server-side only and never appears in your site\'s source code.' => 'Deinen API-Schlüssel findest du im no404-Dashboard auf der Seite mit den Website-Einstellungen. Der Schlüssel wird ausschließlich serverseitig verwendet und taucht nie im Quelltext deiner Website auf.',
	'Caching stops bots that hit the same dead URL over and over from burning through your monthly event quota. A shorter lifetime means higher quota usage.' => 'Der Cache verhindert, dass Bots, die immer wieder dieselbe tote URL aufrufen, dein monatliches Ereigniskontingent aufbrauchen. Je kürzer die Cache-Dauer, desto höher der Verbrauch.',
	'You should not need to touch this — the default, %s, is the right address. Change it only if you host no404 on your own server. Leave the field empty to restore the default.' => 'Hier musst du normalerweise nichts ändern – die Voreinstellung %s ist die richtige Adresse. Ändere sie nur, wenn du no404 auf deinem eigenen Server betreibst. Lässt du das Feld leer, wird die Voreinstellung wiederhergestellt.',
	'Paste your key' => 'Schlüssel hier einfügen',
	'Saved key: %s — leave this field empty to keep it.' => 'Gespeicherter Schlüssel: %s – lass dieses Feld leer, um ihn beizubehalten.',
	'Apply no404 redirects on pages that are not found' => 'no404-Weiterleitungen auf nicht gefundenen Seiten anwenden',
	'Send every match as a 301 (permanent)' => 'Jeden Treffer als 301 (dauerhaft) senden',
	'By default only manually defined redirects and high-scoring matches are sent as 301; speculative matches are sent as 302. A 301 is cached permanently by browsers and cannot be taken back — tick this box only if you are confident your catalogue is complete.' => 'Standardmäßig werden nur manuell angelegte Weiterleitungen und Treffer mit hoher Bewertung als 301 gesendet; unsichere Treffer als 302. Eine 301 wird von Browsern dauerhaft zwischengespeichert und lässt sich nicht zurücknehmen – setze dieses Häkchen nur, wenn du sicher bist, dass dein Katalog vollständig ist.',
	'seconds' => 'Sekunden',
	'Default 3600 (1 hour). Minimum 60, maximum 604800 (7 days).' => 'Voreinstellung 3600 (1 Stunde). Minimum 60, Maximum 604800 (7 Tage).',
	'milliseconds' => 'Millisekunden',
	'If no404 does not answer within this time the request is dropped and your site shows its own 404 page. Visitors are never left waiting.' => 'Antwortet no404 nicht innerhalb dieser Zeit, wird die Anfrage verworfen und deine Website zeigt ihre eigene 404-Seite. Besucher müssen nie warten.',
	'One path prefix per line. URLs starting with these prefixes are never sent to no404. Static files (.css, .js, .png …) and paths such as /wp-admin and /wp-json are excluded automatically.' => 'Ein Pfad-Präfix pro Zeile. URLs, die mit diesen Präfixen beginnen, werden nie an no404 gesendet. Statische Dateien (.css, .js, .png …) sowie Pfade wie /wp-admin und /wp-json sind automatisch ausgeschlossen.',
	'Testing…' => 'Wird getestet …',
	'The test could not be completed. Reload the page and try again.' => 'Der Test konnte nicht abgeschlossen werden. Lade die Seite neu und versuche es erneut.',
	'The plugin is not running yet: no API key has been entered.' => 'Das Plugin läuft noch nicht: Es wurde kein API-Schlüssel eingetragen.',
	'Redirecting is switched off. 404 pages are shown as they are.' => 'Die Weiterleitung ist ausgeschaltet. 404-Seiten werden unverändert angezeigt.',
	'Active. Pages that are not found are redirected server-side.' => 'Aktiv. Nicht gefundene Seiten werden serverseitig weitergeleitet.',
	'Connection test' => 'Verbindungstest',
	'Sends a real request to no404 using your saved settings. Save your changes first.' => 'Sendet mit deinen gespeicherten Einstellungen eine echte Anfrage an no404. Speichere deine Änderungen vorher.',
	'Path to test' => 'Zu testender Pfad',
	'Test the connection' => 'Verbindung testen',
	'You do not have permission to do this.' => 'Du hast keine Berechtigung, das zu tun.',
	'The client could not be initialised.' => 'Der Client konnte nicht initialisiert werden.',
	'Connection succeeded. Suggested target for this path: %1$s (source: %2$s, score: %3$s).' => 'Verbindung erfolgreich. Vorgeschlagenes Ziel für diesen Pfad: %1$s (Quelle: %2$s, Bewertung: %3$s).',
	'Connection succeeded. Your API key is valid; no match was found for this test path, which is what we expect.' => 'Verbindung erfolgreich. Dein API-Schlüssel ist gültig; für diesen Testpfad wurde kein Treffer gefunden – genau das ist zu erwarten.',
	'No API key has been entered. Save your key and try again.' => 'Es wurde kein API-Schlüssel eingetragen. Speichere deinen Schlüssel und versuche es erneut.',
	'The no404 address is empty.' => 'Die no404-Adresse ist leer.',
	'Invalid API key (404). Copy the key from the site settings page in your no404 dashboard; if you rotated the key recently, enter the new one here as well.' => 'Ungültiger API-Schlüssel (404). Kopiere den Schlüssel im no404-Dashboard von der Seite mit den Website-Einstellungen; wenn du den Schlüssel kürzlich erneuert hast, trage hier ebenfalls den neuen ein.',
	'Access denied (403): %s. Your subscription may be inactive, monitoring for this site may be paused, or your account may be suspended.' => 'Zugriff verweigert (403): %s. Möglicherweise ist dein Abonnement inaktiv, die Überwachung dieser Website pausiert oder dein Konto gesperrt.',
	'Access denied (403). Your subscription may be inactive, monitoring for this site may be paused, or your account may be suspended.' => 'Zugriff verweigert (403). Möglicherweise ist dein Abonnement inaktiv, die Überwachung dieser Website pausiert oder dein Konto gesperrt.',
	'Rate limit exceeded, or your monthly event quota is used up (429). Check your quota in the no404 dashboard; if you sent many requests in a short time, try again in a minute.' => 'Anfragelimit überschritten oder dein monatliches Ereigniskontingent ist aufgebraucht (429). Prüfe dein Kontingent im no404-Dashboard; wenn du in kurzer Zeit viele Anfragen gesendet hast, versuche es in einer Minute erneut.',
	'The test path is invalid (422). Enter a path that starts with "/".' => 'Der Testpfad ist ungültig (422). Gib einen Pfad ein, der mit „/“ beginnt.',
	'Could not reach the no404 server: %s. Make sure your server is allowed to make outbound HTTPS requests.' => 'Der no404-Server ist nicht erreichbar: %s. Stelle sicher, dass dein Server ausgehende HTTPS-Anfragen stellen darf.',
	'Could not reach the no404 server. Make sure your server is allowed to make outbound HTTPS requests.' => 'Der no404-Server ist nicht erreichbar. Stelle sicher, dass dein Server ausgehende HTTPS-Anfragen stellen darf.',
	'no404 hit a temporary error (5xx). Your site is unaffected; try again shortly.' => 'Bei no404 ist ein vorübergehender Fehler aufgetreten (5xx). Deine Website ist davon nicht betroffen; versuche es gleich noch einmal.',
	'Unexpected response (HTTP %d).' => 'Unerwartete Antwort (HTTP %d).',
	'The no404 address must be a valid http(s) URL. The previous value has been kept. Leave the field empty to restore the default.' => 'Die no404-Adresse muss eine gültige http(s)-URL sein. Der vorherige Wert wurde beibehalten. Lass das Feld leer, um die Voreinstellung wiederherzustellen.',
);
