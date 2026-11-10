# ZisterneKachel

v1 · 10.11.2026 · Hannes Hacker - KNX Werkstatt

HTML-SDK-Kachel für die Kachel-Visualisierung: Zisterne mit Brunnenpumpe (Zufluss) und Hauswasserwerk (Abfluss).
Stufe 1: nur Anzeige, keine Bedienung. Das Modul liest Variablen, schreibt nie.

## Voraussetzungen

- IP-Symcon ab 7.1 (HTML-SDK)
- Kachel-Visualisierung

## Einrichtung

1. Bibliothek über Module Control einbinden: `https://github.com/knxWerkstatt/html-kacheln`
2. Instanz "Zisterne Kachel" anlegen.
3. Im Instanzformular die Quellvariablen auswählen.
4. Instanz in der Kachel-Visualisierung auf eine Seite legen.

## Instanzformular

| Bereich | Feld | Erwarteter Typ |
|---|---|---|
| Zisterne | Füllstand % | Integer/Float |
| Zisterne | Füllmenge l | Integer/Float |
| Zisterne | Abstand Oberfläche cm | Integer/Float |
| Zisterne | Verbindung + invertieren (Vorgabe an) | Boolean |
| Brunnenpumpe | Automatik Modus | Integer mit Profil |
| Brunnenpumpe | Status Kanal 1 (manuell) | Boolean |
| Brunnenpumpe | Status Kanal 2 (Automatik, über Schwimmer) | Boolean |
| Brunnenpumpe | Leistung Kanal 1 / 2 kW | Integer/Float |
| Brunnenpumpe | Mindestfüllstand % | Integer/Float |
| Brunnenpumpe | Wochenplan | Ereignis |
| Brunnenpumpe | Verbindung + invertieren (Vorgabe aus) | Boolean |
| Hauswasserwerk | Status aus / an | Boolean |
| Hauswasserwerk | Leistung kW | Integer/Float |
| Hauswasserwerk | Verbindung + invertieren (Vorgabe aus) | Boolean |
| Allgemein | Laufschwelle (Vorgabe 0,01 kW) | Float |

## Verhalten

| Punkt | Regel |
|---|---|
| Aktualisierung | jede Aktualisierung einer Quellvariable schickt den kompletten Zustand an die Kachel |
| Pumpe "läuft" | Leistung > Laufschwelle (Brunnenpumpe: Summe beider Kanäle) |
| Pumpe "bereit" | mindestens ein Kanal an, Leistung ≤ Laufschwelle |
| Pumpe "aus" | alle Kanäle aus |
| Verbindung ok | grüner Rahmen |
| Keine Verbindung | roter Rahmen, Stempel "KEINE VERBINDUNG", Werte abgeblendet, keine Bewegung |
| Fehlender Wert | Anzeige "–" |
| Layout | ab 640 px Breite horizontal, darunter vertikal |
