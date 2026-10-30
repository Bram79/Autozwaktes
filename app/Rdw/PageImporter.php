<?php

namespace App\Rdw;

use Illuminate\Support\Facades\DB;

/**
 * Saves one page of RDW rows into the right table. Safe to run twice on the same page.
 */
class PageImporter
{
    /** @param array<int, array<string, string>> $rows */
    public function import(string $dataset, array $rows): int
    {
        return match ($dataset) {
            'defect_codes' => $this->defectCodes($rows),
            'vehicles' => $this->vehicles($rows),
            'fuel' => $this->fuel($rows),
            'defects' => $this->defects($rows),
            default => throw new \InvalidArgumentException("Unknown RDW dataset '{$dataset}'"),
        };
    }

    private function defectCodes(array $rows): int
    {
        $data = array_map(fn ($r) => [
            'code' => $r['gebrek_identificatie'],
            'description' => $r['gebrek_omschrijving'] ?? '',
            'article_number' => $r['gebrek_artikel_nummer'] ?? null,
            'valid_from' => isset($r['ingangsdatum_gebrek_dt']) ? substr($r['ingangsdatum_gebrek_dt'], 0, 10) : null,
            'valid_to' => isset($r['einddatum_gebrek_dt']) ? substr($r['einddatum_gebrek_dt'], 0, 10) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $rows);

        foreach (array_chunk($data, 500) as $chunk) {
            DB::table('defect_codes')->upsert($chunk, ['code'], ['description', 'article_number', 'valid_from', 'valid_to', 'updated_at']);
        }

        return count($data);
    }

    private function vehicles(array $rows): int
    {
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                'license_plate' => $r['kenteken'],
                'brand_raw' => $r['merk'] ?? '',
                'model_raw' => $r['handelsbenaming'] ?? '',
                'build_year' => isset($r['datum_eerste_toelating_dt']) ? (int) substr($r['datum_eerste_toelating_dt'], 0, 4) : null,
            ];
        }

        foreach (array_chunk($data, 1000) as $chunk) {
            DB::table('staging_vehicles')->upsert($chunk, ['license_plate'], ['brand_raw', 'model_raw', 'build_year']);
        }

        return count($data);
    }

    private function fuel(array $rows): int
    {
        $known = $this->knownPlates(array_column($rows, 'kenteken'));

        // group plates per fuel type, so one page needs only a handful of queries
        $groups = [];
        foreach ($rows as $r) {
            if (! isset($known[$r['kenteken']]) || ! isset($r['brandstof_omschrijving'])) {
                continue;
            }
            $column = $r['brandstof_volgnummer'] === '1' ? 'fuel' : 'fuel_2';
            $groups[$column][$r['brandstof_omschrijving']][] = $r['kenteken'];
        }

        foreach ($groups as $column => $byFuel) {
            foreach ($byFuel as $fuel => $plates) {
                foreach (array_chunk($plates, 2000) as $chunk) {
                    DB::table('staging_vehicles')->whereIn('license_plate', $chunk)->update([$column => $fuel]);
                }
            }
        }

        return count($rows);
    }

    private function defects(array $rows): int
    {
        $known = $this->knownPlates(array_column($rows, 'kenteken'));

        $data = [];
        foreach ($rows as $r) {
            if (! isset($known[$r['kenteken']]) || ! isset($r['meld_datum_door_keuringsinstantie_dt'])) {
                continue;
            }
            $data[] = [
                'license_plate' => $r['kenteken'],
                'defect_code' => $r['gebrek_identificatie'],
                'reported_at' => str_replace('T', ' ', substr($r['meld_datum_door_keuringsinstantie_dt'], 0, 19)),
                'defect_count' => (int) ($r['aantal_gebreken_geconstateerd'] ?? 1),
            ];
        }

        foreach (array_chunk($data, 1000) as $chunk) {
            DB::table('staging_defects')->insertOrIgnore($chunk);
        }

        return count($rows);
    }

    /** @return array<string, true> plates from the given list that exist in staging_vehicles */
    private function knownPlates(array $plates): array
    {
        $known = [];
        foreach (array_chunk(array_unique($plates), 5000) as $chunk) {
            foreach (DB::table('staging_vehicles')->whereIn('license_plate', $chunk)->pluck('license_plate') as $plate) {
                $known[$plate] = true;
            }
        }

        return $known;
    }
}
