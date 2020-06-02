# MArs
MAnnheim reservation system (MArs) is a web application used for seat booking in Mannheim University Library.

### Übersicht

Diese Web-Applikation ermöglicht es, nach Anmeldung und Authentisierung
die persönlichen Buchungen von Arbeitsplätzen in der Bibliothek zu
verwalten.

Die Applikation ist für die Bedürfnisse der UB Mannheim entwickelt,
lässt sich aber auch für andere Anwender anpassen.

### Features

- Anzeige der vergangenen Buchungen der letzten Tage (konfigurierbar), Ändern nicht möglich
- Anzeige, Ändern und Löschen der vorgemerkten Buchungen
- Vormerken zusätzlicher Buchungen für die nächsten Tage

Momentan ist maximal eine Buchung pro Tag und Benutzer zulässig.
Die Auswahl der Buchungsmöglichkeiten ist konfigurierbar.

Die Authentisierung erfolgt z. B. durch Benutzerkennung und Passwort (konfigurierbar).
Die Implementierung der UB Mannheim authentisiert mit LDAP.
Administrative Funktionen und Testmöglichkeiten werden mit einem Masterpasswort freigeschaltet.

- Anzeige aller Buchungen
- Initialisieren einer leeren Datenbank
- Erzeugen von zufälligen Buchungen für Testzwecke

Weitere Funktionen wie Löschen alter Buchungen oder Erzeugen von Berichten
können nachgerüstet oder über SQL-Befehle realisiert werden.

### Installation und Technik

Die Applikation ist in PHP realisiert.
Sie kann auf jedem Webserver mit PHP-Unterstützung in TYPO3 und andere HTML-Seiten
als IFrame eingebunden werden:

    <iframe src="/reservation/" height="480" width="640" name="seatreservation">Sitzplatzreservierung</iframe>

Auch ein Aufruf von der Kommandozeile ist möglich.

Alle Buchungsdaten werden in eine Datenbank gesichert.
Aktuell implementiert ist dabei die Anbindung an MariaDB.

    # Create new user and grant all required privileges.
    CREATE USER 'sitzplatzreservierung'@localhost IDENTIFIED BY 'mypassword';
    GRANT ALL PRIVILEGES ON sitzplatzreservierung.* to sitzplatzreservierung@localhost;


Buchungsrestriktionen sind teilweise in der Datenbank realisiert, aktuell nur eine Buchung pro Tag und Benutzer.
Weitere, z. B. Prüfung der Eingaben, lassen sich ebenfalls in der Datenbank realisieren.
Der PHP-Code enthält zusätzliche Prüfungen.

Die Anzeige lässt sich optional per CSS angepassen.

### Offene Punkte

* Projektnamen auswählen
* Benutzerdaten aus ALMA
* falls ecUM: Prüfung auf immer 10 Ziffern?
* Mehrsprachigkeit lässt sich z. B. mit Hilfe von `gettext` nachrüsten.
* Anbindung von E-Mail-Benachrichtigung fehlt noch (php-swiftmailer, php-mail)
* php-ssh2 installieren und statt `exec` verwenden

### Lizenz

(c) 2020 Universitätsbibliothek Mannheim

Diese Software darf frei gemäss [GNU Affero General Public License Version 3 (GNU AGPL v3)](LICENSE) verwendet werden.
