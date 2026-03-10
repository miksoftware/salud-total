<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class CedulasImport implements ToCollection
{
    protected array $cedulas = [];
    protected bool $isFirstRow = true;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $values = $row->toArray();

            // Skip header row if it contains text like "cedula", "identificacion" etc.
            if ($this->isFirstRow) {
                $this->isFirstRow = false;
                $firstVal = strtolower(trim((string)($values[0] ?? '')));
                if (in_array($firstVal, ['cedula', 'cedulas', 'identificacion', 'documento', 'no_identificacion', 'numero', 'no. identificacion', 'no identificacion'])) {
                    continue; // Skip header
                }
            }

            // Get the first cell value
            $cedula = $values[0] ?? null;

            if ($cedula !== null) {
                $cleaned = preg_replace('/[^0-9]/', '', trim((string)$cedula));
                if (!empty($cleaned) && strlen($cleaned) >= 5) {
                    $this->cedulas[] = $cleaned;
                }
            }
        }
    }

    public function getCedulas(): array
    {
        return array_values(array_unique($this->cedulas));
    }
}
