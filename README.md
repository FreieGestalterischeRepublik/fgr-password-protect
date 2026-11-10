# FGR Password Protect

Ein Plugin der Freien Gestalterischen Republik. Schützt die gesamte WordPress-Website mit einem Passwort.

## Funktionen

- Ein gemeinsames Passwort für die ganze Website (gespeichert nur als Hash)
- Zwei Designs für die Passwortseite:
  - **FGR-Design** – lila Seite mit FGR-Logo, wie die Under-Construction-Seite von FGR Maintenance
  - **Individuell** – eigenes Logo, Hintergrundfarbe oder -bild, Text- und Buttonfarben, Schrift
- Überschrift, Text und Button-Beschriftung frei wählbar
- „Angemeldet bleiben“ mit einstellbarer Dauer (0 = nur bis zum Schließen des Browsers)
- Administratoren haben immer Zugang, andere eingeloggte Benutzer wahlweise
- IP-Freigabe (eine IP pro Zeile)
- Optional: REST-API ebenfalls schützen
- Schutz gegen Durchprobieren: nach 10 Fehlversuchen ist die Eingabe pro IP 15 Minuten gesperrt
- Ein neues Passwort meldet alle bisherigen Besucher ab
- Vorschau der Passwortseite für Administratoren
- Login (`wp-login.php` bzw. die Adresse von FGR Hide Login) bleibt immer erreichbar
- Seite wird mit `noindex` und ohne Cache ausgeliefert

## Bedienung

Backend → **FGR Plugins → Password Protect**

## Updates

Updates kommen über die zentrale FGR-Update-API und werden automatisch installiert.

## Anforderungen

PHP 7.4+, WordPress 6.0+
