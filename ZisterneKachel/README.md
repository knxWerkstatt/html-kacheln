# ZisterneKachel

v3 · 16.11.2026 · Hannes Hacker - KNX Werkstatt

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
| Brunnenpumpe | Leistung Kanal 1 / 2 W | Integer/Float |
| Brunnenpumpe | Mindestfüllstand % (Anzeige in der Zisterne) | Integer/Float |
| Brunnenpumpe | Wochenplan | Ereignis |
| Brunnenpumpe | Verbindung + invertieren (Vorgabe aus) | Boolean |
| Hauswasserwerk | Status aus / an | Boolean |
| Hauswasserwerk | Leistung W | Integer/Float |
| Hauswasserwerk | Verbindung + invertieren (Vorgabe aus) | Boolean |
| Allgemein | Laufschwelle (Vorgabe 10 W) | Float |

## Verhalten

| Punkt | Regel |
|---|---|
| Aktualisierung | jede Aktualisierung einer Quellvariable schickt den kompletten Zustand an die Kachel |
| Pumpe "läuft" | Leistung in W > Laufschwelle (Brunnenpumpe: Summe beider Kanäle) |
| Pumpe "bereit" | mindestens ein Kanal an, Leistung ≤ Laufschwelle |
| Pumpe "aus" | alle Kanäle aus |
| Verbindung ok | grüner Rahmen |
| Keine Verbindung | roter Rahmen, Stempel "KEINE VERBINDUNG", Werte abgeblendet, keine Bewegung |
| Anzeigetexte | 1:1 aus der Symcon-Darstellung (GetValueFormatted); Leistung Brunnenpumpe = Text des Kanals mit der höheren Leistung |
| Brunnenpumpe aus / an | an, wenn Kanal 1 oder Kanal 2 an ist |
| Automatik Modus | Symcon-Text + "(freigegeben)" bei Kanal 2 an, "(gesperrt)" bei Kanal 2 aus |
| Handbetrieb | "aktiv" (fett rot) bei Kanal 1 an, sonst "inaktiv" |
| Fehlender Wert | Anzeige "–" |
| Layout | ab 640 px Breite horizontal, darunter vertikal |
