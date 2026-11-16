<?php

declare(strict_types=1);

/**
 * PoolKachel - HTML-SDK-Kachel für den Pool: Poolinfo (Bayrol) und Poolbedienung (Poolpumpe)
 * Anzeige + Zahnrad bei der Poolbedienung, das per openObject eine Dummy-Instanz mit dem Schalter öffnet.
 * Das Modul selbst schreibt nie.
 *
 * Hannes Hacker - KNX Werkstatt
 * Version: v1
 * Erstellt: 16.11.2026, 19:26
 *
 * Grundlage: pool_variablenliste_v1.md, pool_nachrichtenvertrag_v1.md, Aufbau wie ZisterneKachel v5
 * - Alle Quellvariablen werden im Instanzformular ausgewählt, keine IDs im Code.
 * - Bei jeder Aktualisierung einer Quellvariable wird der komplette Zustand an die Kachel geschickt.
 * - Anzeigetexte 1:1 per GetValueFormatted; Wertfarben aus der Symcon-Darstellung (IPS_GetVariablePresentation).
 * - Berechnungen (Pumpenzustand, Polarität Verbindung, Farbe, neueste Aktualisierung) erfolgen nur hier.
 * - Fehlende Werte werden als null geschickt, die Kachel zeigt "–".
 */
class PoolKachel extends IPSModule
{
    // Poolinfo-Werte: Eigenschaft => Schlüssel in der Nachricht
    private const INFO_VALUES = [
        'InfoTemp'  => 'temp',
        'InfoPh'    => 'ph',
        'InfoRedox' => 'redox',
        'InfoSalt'  => 'salt'
    ];

    // Eigenschaften, deren Variablen beobachtet werden (VM_UPDATE)
    private const WATCHED = [
        'InfoTemp', 'InfoPh', 'InfoRedox', 'InfoSalt', 'InfoOnline',
        'PumpStatus', 'PumpPower', 'PumpEnergy', 'PumpOnline'
    ];

    public function Create()
    {
        // Diese Zeile nicht löschen
        parent::Create();

        foreach (self::WATCHED as $property) {
            $this->RegisterPropertyInteger($property, 0);
        }

        // Einstellungs-Objekt der Poolbedienung (Dummy-Instanz mit Link auf den Schalter); 0 = kein Zahnrad
        $this->RegisterPropertyInteger('PumpSettings', 0);

        // Haltezeit Zahnrad in ganzen Sekunden; 0 = Öffnen per kurzem Antippen
        $this->RegisterPropertyInteger('SettingsHoldSeconds', 2);

        // Polarität der Verbindungsvariablen: Watchdog "Alarm" = true bei Problem, Shelly "Erreichbar" = true bei ok
        $this->RegisterPropertyBoolean('InfoOnlineInvert', true);
        $this->RegisterPropertyBoolean('PumpOnlineInvert', false);

        // Pumpe gilt als "läuft", wenn die Leistung (Watt) diese Schwelle überschreitet
        $this->RegisterPropertyFloat('RunThresholdW', 10);

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
     * Kompletter Zustand nach Nachrichtenvertrag pool v1.
     */
    private function GetFullUpdateMessage(): string
    {
        // Poolinfo: je Wert Text und Farben; Zeitstempel = neueste Aktualisierung der vier Werte
        $info = [
            'online' => $this->ReadOnline('InfoOnline', 'InfoOnlineInvert'),
            'ts'     => $this->NewestUpdate(array_keys(self::INFO_VALUES))
        ];
        foreach (self::INFO_VALUES as $property => $key) {
            $colors = $this->ReadColors($property);
            $info[$key] = [
                'txt' => $this->ReadFormatted($property),
                'bg'  => $colors['bg'],
                'fg'  => $colors['fg']
            ];
        }

        $pumpStatus = $this->ReadValue('PumpStatus');
        $pumpStatus = $pumpStatus === null ? null : (bool) $pumpStatus;
        $pumpPower = $this->ReadFloat('PumpPower');

        $message = [
            'ui' => [
                'hold' => max(0, $this->ReadPropertyInteger('SettingsHoldSeconds'))
            ],
            'info'  => $info,
            'pumpe' => [
                'online' => $this->ReadOnline('PumpOnline', 'PumpOnlineInvert'),
                'ts'     => $this->ReadUpdated('PumpStatus'),
                'state'  => $this->PumpState($pumpStatus, $pumpPower),
                'on'     => $pumpStatus,
                'power'  => $pumpPower,
                'cfg'    => $this->ReadObjectID('PumpSettings'),
                'txt'    => [
                    'power'  => $this->ReadFormatted('PumpPower'),
                    'energy' => $this->ReadFormatted('PumpEnergy')
                ]
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

    private function NewestUpdate(array $properties): ?int
    {
        $times = array_filter(array_map([$this, 'ReadUpdated'], $properties), function ($t) {
            return $t !== null && $t > 0;
        });
        return count($times) ? max($times) : null;
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

    private function ReadObjectID(string $property): ?int
    {
        $objectID = $this->ReadPropertyInteger($property);
        return ($objectID > 0 && IPS_ObjectExists($objectID)) ? $objectID : null;
    }

    /**
     * Farben aus der Symcon-Darstellung (ab Symcon 8.1).
     * Intervall mit Min <= Rohwert <= Max: ColorValue (Hintergrund) bzw. ContentColorValue (Text), wenn aktiv;
     * sonst Vorgaben COLOR / CONTENT_COLOR, wenn != -1; sonst null.
     */
    private function ReadColors(string $property): array
    {
        $none = ['bg' => null, 'fg' => null];
        $variableID = $this->VariableID($property);
        if (!$variableID || !function_exists('IPS_GetVariablePresentation')) {
            return $none;
        }
        try {
            $p = IPS_GetVariablePresentation($variableID);
        } catch (Throwable $e) {
            return $none;
        }
        if (!is_array($p)) {
            return $none;
        }

        $bg = $this->ColorHex($p['COLOR'] ?? -1);
        $fg = $this->ColorHex($p['CONTENT_COLOR'] ?? -1);

        if (!empty($p['INTERVALS_ACTIVE'])) {
            $intervals = $p['INTERVALS'] ?? [];
            if (is_string($intervals)) {
                $intervals = json_decode($intervals, true);
            }
            $value = GetValue($variableID);
            if (is_array($intervals) && is_numeric($value)) {
                foreach ($intervals as $i) {
                    if (!isset($i['IntervalMinValue'], $i['IntervalMaxValue'])) {
                        continue;
                    }
                    if ($value >= $i['IntervalMinValue'] && $value <= $i['IntervalMaxValue']) {
                        if (!empty($i['ColorActive'])) {
                            $bg = $this->ColorHex($i['ColorValue'] ?? -1);
                        }
                        if (!empty($i['ContentColorActive'])) {
                            $fg = $this->ColorHex($i['ContentColorValue'] ?? -1);
                        }
                        break;
                    }
                }
            }
        }
        return ['bg' => $bg, 'fg' => $fg];
    }

    private function ColorHex($color): ?string
    {
        if (!is_numeric($color) || (int) $color < 0) {
            return null;
        }
        return sprintf('#%06x', ((int) $color) & 0xFFFFFF);
    }

    /**
     * Pumpenzustand: run = Leistung > Schwelle, ready = an ohne Leistung, off = aus.
     * Liegt gar kein Wert vor, wird null geschickt.
     */
    private function PumpState(?bool $status, ?float $power): ?string
    {
        if ($status === null && $power === null) {
            return null;
        }
        if ($power !== null && $power > $this->ReadPropertyFloat('RunThresholdW')) {
            return 'run';
        }
        return $status === true ? 'ready' : 'off';
    }
}
