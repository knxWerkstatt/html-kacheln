<?php

declare(strict_types=1);

/**
 * EnergieKachel - HTML-SDK-Kachel Energiefluss einer Inselanlage
 * PV 3 kW und PV 6 kW -> Hybrid-WR mit Batterie -> Verbrauch -> Brunnenpumpe, Hauswasserwerk, Poolpumpe, Rest.
 * Nur Anzeige, keine Bedienung. Das Modul selbst schreibt nie.
 *
 * Hannes Hacker - KNX Werkstatt
 * Version: v1
 * Erstellt: 16.11.2026, 20:58
 *
 * Grundlage: energie_variablenliste_v1.md, energie_nachrichtenvertrag_v1.md, Aufbau wie PoolKachel v2
 * - Alle Quellvariablen werden im Instanzformular ausgewählt, keine IDs im Code.
 * - Bei jeder Aktualisierung einer Quellvariable wird der komplette Zustand an die Kachel geschickt.
 * - Anzeigetexte 1:1 per GetValueFormatted; einzige Ausnahme: Restverbrauch (keine Variable), fest formatiert.
 * - Berechnungen (Polarität Verbindung, Restverbrauch, Gültigkeit Verbrauch, Zeitstempel) erfolgen nur hier.
 * - Fehlende Werte werden als null geschickt, die Kachel zeigt "–".
 */
class EnergieKachel extends IPSModule
{
    // Quellen mit Verbindung: Schlüssel => [Leistungs-Eigenschaften, Verbindungs-Eigenschaft, Kurzname]
    private const SOURCES = [
        'pv3'     => [['Pv3Power'], 'Pv3Online', 'PV 3 kW'],
        'pv6'     => [['Pv6Power'], 'Pv6Online', 'PV 6 kW'],
        'wr'      => [['WrPower'], 'WrOnline', 'SI 8'],
        'brunnen' => [['WellPower1', 'WellPower2'], 'WellOnline', 'Brunnenpumpe'],
        'hww'     => [['HousePower'], 'HouseOnline', 'Hauswasserwerk'],
        'pool'    => [['PoolPower'], 'PoolOnline', 'Poolpumpe']
    ];

    // Eigenschaften, deren Variablen beobachtet werden (VM_UPDATE)
    private const WATCHED = [
        'Pv3Power', 'Pv3Online', 'Pv6Power', 'Pv6Online',
        'WrPower', 'WrSoc', 'WrOnline', 'LoadPower',
        'WellPower1', 'WellPower2', 'WellOnline',
        'HousePower', 'HouseOnline', 'PoolPower', 'PoolOnline'
    ];

    public function Create()
    {
        // Diese Zeile nicht löschen
        parent::Create();

        foreach (self::WATCHED as $property) {
            $this->RegisterPropertyInteger($property, 0);
        }

        // Polarität der Verbindungsvariablen: Vorgabe true = verbunden (Shelly "Erreichbar");
        // invertieren nur für Quellen mit true = Störung (Watchdog "Alarm")
        foreach (self::SOURCES as [$powers, $online, $name]) {
            $this->RegisterPropertyBoolean($online . 'Invert', false);
        }

        // Fluss und Lade-/Entladerichtung erst oberhalb dieser Schwelle (Watt)
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
     * Kompletter Zustand nach Nachrichtenvertrag energie v1.
     */
    private function GetFullUpdateMessage(): string
    {
        $online = [];
        foreach (self::SOURCES as $key => [$powers, $onlineProperty, $name]) {
            $online[$key] = $this->ReadOnline($onlineProperty, $onlineProperty . 'Invert');
        }

        // Brunnenpumpe: Summe beider Kanäle; Text des Kanals mit der höheren Leistung (bei Gleichstand Kanal 2)
        $well1 = $this->ReadFloat('WellPower1');
        $well2 = $this->ReadFloat('WellPower2');
        $wellPower = $this->SumOrNull([$well1, $well2]);
        $wellText = ($well1 ?? -1) > ($well2 ?? -1)
            ? $this->ReadFormatted('WellPower1')
            : ($well2 !== null ? $this->ReadFormatted('WellPower2') : null);

        $cons = [
            'brunnen' => [
                'online' => $online['brunnen'],
                'ts'     => $this->NewestUpdate(['WellPower1', 'WellPower2']),
                'txt'    => $wellText,
                'power'  => $wellPower
            ],
            'hww'  => $this->Box('HousePower', $online['hww']),
            'pool' => $this->Box('PoolPower', $online['pool'])
        ];

        // Verbrauch: Text immer; gültig nur, wenn PV 3, PV 6 und SI 8 verbunden sind
        $loadPower = $this->ReadFloat('LoadPower');
        $load = [
            'valid' => $online['pv3'] === true && $online['pv6'] === true && $online['wr'] === true,
            'txt'   => $this->ReadFormatted('LoadPower'),
            'power' => $loadPower
        ];

        // Restverbrauch: nur wenn alle sechs Quellen verbunden sind und alle Werte vorliegen
        $missing = [];
        $lost = [];
        foreach (self::SOURCES as $key => [$powers, $onlineProperty, $name]) {
            if ($online[$key] !== true) {
                $missing[] = $name;
            }
            if ($online[$key] === false) {
                $lost[] = $name;
            }
        }
        $pumpPowers = [$wellPower, $cons['hww']['power'], $cons['pool']['power']];
        $restPower = null;
        if (!count($missing) && $loadPower !== null && !in_array(null, $pumpPowers, true)) {
            $restPower = $loadPower - array_sum($pumpPowers);
        }

        $wr = $this->Box('WrPower', $online['wr']);
        $wr['soc'] = [
            'txt' => $this->ReadFormatted('WrSoc'),
            'raw' => $this->ReadFloat('WrSoc')
        ];

        $message = [
            'ui'   => ['thr' => $this->ReadPropertyFloat('RunThresholdW')],
            'pv3'  => $this->Box('Pv3Power', $online['pv3']),
            'pv6'  => $this->Box('Pv6Power', $online['pv6']),
            'wr'   => $wr,
            'load' => $load,
            'cons' => $cons,
            'rest' => [
                'txt'     => $restPower === null ? null : $this->FormatPower($restPower),
                'power'   => $restPower,
                'missing' => $missing
            ],
            'lost' => $lost,
            'ts'   => $this->NewestUpdate([
                'Pv3Power', 'Pv6Power', 'WrPower', 'WrSoc', 'LoadPower',
                'WellPower1', 'WellPower2', 'HousePower', 'PoolPower'
            ])
        ];

        return json_encode($message);
    }

    // ---------------------------------------------------------------------
    // Hilfsfunktionen: lesen ausschließlich, schreiben nie
    // ---------------------------------------------------------------------

    private function Box(string $powerProperty, ?bool $online): array
    {
        return [
            'online' => $online,
            'ts'     => $this->ReadUpdated($powerProperty),
            'txt'    => $this->ReadFormatted($powerProperty),
            'power'  => $this->ReadFloat($powerProperty)
        ];
    }

    /**
     * Restverbrauch hat keine Variable und damit keine Darstellung:
     * |W| < 1000 -> "123 W" (ganzzahlig), sonst "1,23 kW".
     */
    private function FormatPower(float $watt): string
    {
        if (abs($watt) < 1000) {
            return (string) (int) round($watt) . ' W';
        }
        return number_format($watt / 1000, 2, ',', '') . ' kW';
    }

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

    private function SumOrNull(array $values): ?float
    {
        $present = array_filter($values, function ($v) {
            return $v !== null;
        });
        return count($present) ? (float) array_sum($present) : null;
    }
}
