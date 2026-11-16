# EnergieKachel

v2 · 16.11.2026, 21:19 · Hannes Hacker - KNX Werkstatt

v2: Auge (Detailansicht) je Kasten.

HTML-SDK-Kachel für die Kachel-Visualisierung: Energiefluss einer Inselanlage ohne Netz.
Von oben nach unten: PV 3 kW und PV 6 kW, Hybrid-WR mit Batterie, Verbrauch, darunter Brunnenpumpe, Hauswasserwerk,
Poolpumpe und Restverbraucher. Jede Quelle hat eine eigene Verbindungsanzeige. Je Kasten kann ein Auge per openObject
eine frei wählbare Detailansicht öffnen (z. B. Dummy-Instanz mit Links).
Das Modul liest Variablen, schreibt nie.

## Voraussetzungen

- IP-Symcon ab 7.1 (HTML-SDK); Auge ab 8.2 (openObject)
- Kachel-Visualisierung

## Einrichtung

1. Bibliothek über Module Control einbinden: `https://github.com/knxWerkstatt/html-kacheln`
2. Instanz "Energie Kachel" anlegen.
3. Im Instanzformular die Quellvariablen auswählen; bei Watchdog-Alarmen "Invertieren" anhaken.
4. Instanz in der Kachel-Visualisierung auf eine Seite legen.

## Instanzformular

| Bereich | Feld | Erwarteter Typ |
|---|---|---|
| PV 3 kW / PV 6 kW | Leistung W | Integer/Float |
| Hybrid-WR mit Batterie | Batterieleistung W (positiv = Entladen) | Integer/Float |
| Hybrid-WR mit Batterie | Ladestand % | Integer/Float |
| Verbrauch | Verbrauch W (Eigenverbrauch) | Integer/Float |
| Brunnenpumpe | Leistung Kanal 1 W, Leistung Kanal 2 W | Integer/Float |
| Hauswasserwerk / Poolpumpe | Leistung W | Integer/Float |
| je Quelle (außer Verbrauch) | Verbindung, true = verbunden | Boolean |
| je Quelle (außer Verbrauch) | Invertieren (Vorgabe aus; nur für Quellen mit true = Störung, z. B. Watchdog-Alarm) | Boolean |
| je Kasten (auch Restverbraucher) | Detailansicht (Auge), leer = kein Auge | beliebiges Objekt |
| Allgemein | Auge halten bis Detailansicht öffnet (Vorgabe 2 s, 0 = sofort antippen) | Integer |
| Allgemein | Schwelle für Fluss und Lade-/Entladerichtung (Vorgabe 10 W) | Float |

## Verhalten

| Punkt | Regel |
|---|---|
| Aktualisierung | jede Aktualisierung einer Quellvariable schickt den kompletten Zustand an die Kachel |
| Anzeigetexte | 1:1 aus der Symcon-Darstellung (GetValueFormatted), inkl. Einheit |
| Restverbraucher | Verbrauch minus Summe der drei Pumpen; Text fest formatiert (unter 1000 W "123 W", sonst "1,23 kW"); "–", sobald eine der sechs Quellen nicht verbunden oder unbekannt ist |
| Verbrauch | Text immer; ist PV 3, PV 6 oder WR nicht verbunden: abgeblendet, "Quelle getrennt", kein Fluss |
| Batterie | > Schwelle entlädt (Pfeil zum WR), < -Schwelle lädt (Pfeil zur Batterie), sonst ruht |
| Brunnenpumpe | Leistung = Summe beider Kanäle; Text des Kanals mit der höheren Leistung |
| Fluss | Leitungen und Laufanzeige nur bei Leistung > Schwelle und bestehender Verbindung |
| Verbindung ok | grüner Rahmen, "verbunden · Zeitpunkt der letzten Aktualisierung" |
| Keine Verbindung | nur der betroffene Kasten: roter Rahmen, Stempel "KEINE VERBINDUNG", Werte abgeblendet, "Stand …" |
| Verbindung nicht eingetragen | neutraler Rahmen, "Verbindung unbekannt" |
| Gesamt-Fußzeile | getrennte Quellen und neueste Aktualisierung aller Werte |
| Auge | Fußzeile rechts, nur sichtbar wenn ein Objekt eingetragen ist; Halten öffnet es; Loslassen oder Verrutschen bricht ab; bei 0 s öffnet kurzes Antippen; auch bei getrennter Verbindung bedienbar |
| Zeitstempel | heute "HH:MM", sonst "TT.MM. HH:MM" |
| Fehlender Wert | Anzeige "–" |
| Layout | ab 640 px Breite PV nebeneinander und Verbraucher in einer Reihe; darunter alles untereinander als Zeilen |
