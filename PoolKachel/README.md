# PoolKachel

v1 · 16.11.2026, 19:26 · Hannes Hacker - KNX Werkstatt

HTML-SDK-Kachel für die Kachel-Visualisierung: stilisierter Pool mit zwei getrennten Bereichen.
"Poolinfo" zeigt die Wasserwerte (z. B. Bayrol über MQTT-Bridge), "Poolbedienung" die Poolpumpe (z. B. Shelly).
Jeder Bereich hat eine eigene Verbindungsanzeige. Das Zahnrad der Poolbedienung öffnet per openObject ein frei wählbares Objekt
(z. B. Dummy-Instanz mit Link auf den Pumpenschalter). Das Modul liest Variablen, schreibt nie.

## Voraussetzungen

- IP-Symcon ab 7.1 (HTML-SDK); Wertfarben ab 8.1 (IPS_GetVariablePresentation), Zahnrad ab 8.2 (openObject)
- Kachel-Visualisierung

## Einrichtung

1. Bibliothek über Module Control einbinden: `https://github.com/knxWerkstatt/html-kacheln`
2. Instanz "Pool Kachel" anlegen.
3. Im Instanzformular die Quellvariablen auswählen.
4. Instanz in der Kachel-Visualisierung auf eine Seite legen.

## Instanzformular

| Bereich | Feld | Erwarteter Typ |
|---|---|---|
| Poolinfo | Wassertemperatur | Integer/Float |
| Poolinfo | pH | Integer/Float |
| Poolinfo | Redox | Integer/Float |
| Poolinfo | Salzgehalt | Integer/Float |
| Poolinfo | Verbindung + invertieren (Vorgabe an, Watchdog "Alarm") | Boolean |
| Poolbedienung | Status aus / an | Boolean |
| Poolbedienung | Leistung W | Integer/Float |
| Poolbedienung | Energie gesamt | Integer/Float |
| Poolbedienung | Verbindung + invertieren (Vorgabe aus, Shelly "Erreichbar") | Boolean |
| Poolbedienung | Bedienung (Zahnrad), leer = kein Zahnrad | beliebiges Objekt |
| Allgemein | Laufschwelle (Vorgabe 10 W) | Float |
| Allgemein | Zahnrad halten bis Bedienung öffnet (Vorgabe 2 s, 0 = sofort antippen) | Integer |

## Verhalten

| Punkt | Regel |
|---|---|
| Aktualisierung | jede Aktualisierung einer Quellvariable schickt den kompletten Zustand an die Kachel |
| Anzeigetexte | 1:1 aus der Symcon-Darstellung (GetValueFormatted), inkl. Einheit |
| Wertfarben | aus der Darstellung: Intervall mit Min ≤ Wert ≤ Max liefert Hintergrund (ColorValue) und Text (ContentColorValue), wenn aktiv; sonst Vorgabefarben; sonst keine |
| Textfarbe fehlt | bei gesetztem Hintergrund Schwarz oder Weiß nach Helligkeit |
| Poolinfo "aktualisiert" | neuester Aktualisierungszeitpunkt der vier Werte |
| Pumpe "läuft" | Leistung in W > Laufschwelle |
| Pumpe "bereit" | an, Leistung ≤ Laufschwelle |
| Pumpe "aus" | aus |
| Verbindung ok | grüner Rahmen des Bereichs |
| Keine Verbindung | nur der betroffene Bereich: roter Rahmen, Stempel "KEINE VERBINDUNG", Werte abgeblendet, keine Bewegung |
| Verbindung nicht eingetragen | neutraler Rahmen, "Verbindung unbekannt" |
| Bewegung | Wellen nur bei Poolinfo-Verbindung; Rotor, Leitung und Blasen nur bei laufender Pumpe mit Verbindung |
| Fehlender Wert | Anzeige "–" |
| Zahnrad | Fußzeile der Poolbedienung, nur sichtbar wenn ein Objekt eingetragen ist; Halten öffnet es; Loslassen oder Verrutschen bricht ab; bei 0 s öffnet kurzes Antippen |
| Oberer Kachelbereich | bleibt ohne Bedienelemente (am PC fängt Symcon dort Klicks ab) |
| Layout | ab 640 px Breite nebeneinander, darunter Pool oben, Pumpe unten |
