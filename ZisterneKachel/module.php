<?php

declare(strict_types=1);

/**
 * ZisterneKachel - HTML-SDK-Kachel für Zisterne, Brunnenpumpe und Hauswasserwerk
 * Stufe 1: nur Anzeige (Modul -> Kachel), keine Bedienung.
 *
 * Hannes Hacker - KNX Werkstatt
 * Version: v1
 * Erstellt: 10.11.2026, 11:23
 *
 * Grundlage: variablenliste_zisterne.md (v1), nachrichtenvertrag_zisterne.md (v1)
 * - Alle Quellvariablen werden im Instanzformular ausgewählt, keine IDs im Code.
 * - Bei jeder Aktualisierung einer Quellvariable wird der komplette Zustand an die Kachel geschickt.
 * - Berechnungen (Pumpenzustand, Leistungssumme, Polarität Verbindung) erfolgen nur hier, nicht in der Kachel.
 * - Fehlende Werte werden als null geschickt, die Kachel zeigt "–".
 */
class ZisterneKachel extends IPSModule
{
    // Eigenschaften, deren Variablen beobachtet werden (VM_UPDATE)
    private const WATCHED = [
        'TankLevel', 'TankFill', 'TankDistance', 'TankOnline',
        'WellMode', 'WellStatus1', 'WellStatus2', 'WellPower1', 'WellPower2', 'WellMinLevel', 'WellOnline',
        'HouseStatus', 'HousePower', 'HouseOnline'
    ];

    public function Create()
    {
        // Diese Zeile nicht löschen
        parent::Create();

        foreach (self::WATCHED as $property) {
            $this->RegisterPropertyInteger($property, 0);
        }
        $this->RegisterPropertyInteger('WellSchedule', 0);

        // Polarität der Verbindungsvariablen: Watchdog "Alarm" = true bei Problem, Shelly "Erreichbar" = true bei ok
        $this->RegisterPropertyBoolean('TankOnlineInvert', true);
        $this->RegisterPropertyBoolean('WellOnlineInvert', false);
        $this->RegisterPropertyBoolean('HouseOnlineInvert', false);

        // Pumpe gilt als "läuft", wenn die Leistung diese Schwelle überschreitet
        $this->RegisterPropertyFloat('RunThreshold', 0.01);

        // Visualisierungstyp 1 = HTML-SDK
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();

        // Alte Registrierungen entfernen
        foreach ($this->GetMessageList() as $senderID => $messageIDs) {
            foreach ($messageIDs as $messageID) {
                $this->UnregisterMessage($senderID, $messageID);
            }
        }

        // Für alle ausgewählten Quellvariablen auf Aktualisierung anmelden
        foreach (self::WATCHED as $property) {
            $variableID = $this->ReadPropertyInteger($property);
            if ($variableID > 0 && IPS_VariableExists($variableID)) {
                $this->RegisterMessage($variableID, VM_UPDATE);
            }
        }

        $this->SetStatus(102);

        // Einstellungen können sich geändert haben: kompletten Zustand schicken
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message === VM_UPDATE) {
            $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
        }
    }

    public function GetVisualizationTile()
    {
        // Erste Anzeige: dieselbe Nachricht wie bei Updates direkt mitgeben.
        // json_encode auf den JSON-String, damit er als JS-String sauber escaped wird.
        // Das Skript muss hinter module.html stehen, weil handleMessage dort definiert wird.
        $initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ');</script>';
        $module = file_get_contents(__DIR__ . '/module.html');
        return $module . $initialHandling;
    }

    /**
     * Kompletter Zustand nach Nachrichtenvertrag v1.
     */
    private function GetFullUpdateMessage(): string
    {
        $wellStatus1 = $this->ReadValue('WellStatus1');
        $wellStatus2 = $this->ReadValue('WellStatus2');
        $wellPower = $this->SumOrNull([$this->ReadFloat('WellPower1'), $this->ReadFloat('WellPower2')]);

        $houseStatus = $this->ReadValue('HouseStatus');
        $housePower = $this->ReadFloat('HousePower');

        $message = [
            'tank' => [
                'online' => $this->ReadOnline('TankOnline', 'TankOnlineInvert'),
                'ts'     => $this->ReadUpdated('TankLevel'),
                'level'  => $this->ReadValue('TankLevel'),
                'fill'   => $this->ReadValue('TankFill'),
                'dist'   => $this->ReadValue('TankDistance')
            ],
            'brunnen' => [
                'online' => $this->ReadOnline('WellOnline', 'WellOnlineInvert'),
                'ts'     => $this->ReadUpdated('WellStatus2'),
                'state'  => $this->PumpState([$wellStatus1, $wellStatus2], $wellPower),
                'mode'   => $this->ReadFormatted('WellMode'),
                'auto'   => $wellStatus2,
                'hand'   => $wellStatus1,
                'plan'   => $this->ReadNextRun('WellSchedule'),
                'min'    => $this->ReadValue('WellMinLevel'),
                'power'  => $wellPower
            ],
            'haus' => [
                'online' => $this->ReadOnline('HouseOnline', 'HouseOnlineInvert'),
                'ts'     => $this->ReadUpdated('HouseStatus'),
                'state'  => $this->PumpState([$houseStatus], $housePower),
                'on'     => $houseStatus,
                'power'  => $housePower
            ]
        ];

        return json_encode($message);
    }

    // ---------------------------------------------------------------------
    // Hilfsfunktionen: lesen ausschließlich, schreiben nie
    // ---------------------------------------------------------------------

    private function VariableID(string $property): int
    {
        $variableID = $this->ReadPropertyInteger($property);
        return ($variableID > 0 && IPS_VariableExists($variableID)) ? $variableID : 0;
    }

    private function ReadValue(string $property)
    {
        $variableID = $this->VariableID($property);
        return $variableID ? GetValue($variableID) : null;
    }

    private function ReadFloat(string $property): ?float
    {
        $value = $this->ReadValue($property);
        return $value === null ? null : (float) $value;
    }

    private function ReadFormatted(string $property): ?string
    {
        $variableID = $this->VariableID($property);
        return $variableID ? GetValueFormatted($variableID) : null;
    }

    private function ReadUpdated(string $property): ?int
    {
        $variableID = $this->VariableID($property);
        return $variableID ? IPS_GetVariable($variableID)['VariableUpdated'] : null;
    }

    private function ReadOnline(string $property, string $invertProperty): ?bool
    {
        $value = $this->ReadValue($property);
        if ($value === null) {
            return null;
        }
        $value = (bool) $value;
        return $this->ReadPropertyBoolean($invertProperty) ? !$value : $value;
    }

    private function ReadNextRun(string $property): ?int
    {
        $eventID = $this->ReadPropertyInteger($property);
        if ($eventID <= 0 || !IPS_EventExists($eventID)) {
            return null;
        }
        $nextRun = IPS_GetEvent($eventID)['NextRun'];
        return $nextRun > 0 ? $nextRun : null;
    }

    private function SumOrNull(array $values): ?float
    {
        $present = array_filter($values, function ($v) {
            return $v !== null;
        });
        return count($present) ? (float) array_sum($present) : null;
    }

    /**
     * Pumpenzustand: run = Leistung > Schwelle, ready = ein Kanal an ohne Leistung, off = alle Kanäle aus.
     * Liegt gar kein Wert vor, wird null geschickt.
     */
    private function PumpState(array $statuses, ?float $power): ?string
    {
        $present = array_filter($statuses, function ($v) {
            return $v !== null;
        });
        if (!count($present) && $power === null) {
            return null;
        }
        if ($power !== null && $power > $this->ReadPropertyFloat('RunThreshold')) {
            return 'run';
        }
        return in_array(true, $present, true) ? 'ready' : 'off';
    }
}
