<?php

namespace App\Rdw;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Small wrapper around the RDW open data (Socrata) API.
 */
class RdwClient
{
    /** @return array<int, array<string, string>> */
    /** @param string[] $extraWhere extra SoQL conditions, joined with AND */
    public function fetchPage(string $dataset, int $offset, int $limit, array $extraWhere = []): array
    {
        $config = $this->datasetConfig($dataset);

        $query = [
            '$select' => implode(',', array_merge($config['columns'], $config['optional_columns'])),
            '$order' => ':id', // a fixed order is needed for stable paging
            '$limit' => $limit,
            '$offset' => $offset,
        ];

        if ($where = $this->where($config, $extraWhere)) {
            $query['$where'] = $where;
        }

        return $this->get("/resource/{$config['id']}.json", $query);
    }

    /** @param string[] $extraWhere */
    public function count(string $dataset, array $extraWhere = []): int
    {
        $config = $this->datasetConfig($dataset);

        $query = ['$select' => 'count(*)'];
        if ($where = $this->where($config, $extraWhere)) {
            $query['$where'] = $where;
        }

        $rows = $this->get("/resource/{$config['id']}.json", $query);

        return (int) ($rows[0]['count'] ?? 0);
    }

    /**
     * Number of passenger cars per trade name, RDW type code and first-registration year for one brand.
     * (Grouped by the RDW itself, so this is a small answer, not millions of rows.)
     *
     * @return array<int, array{handelsbenaming: string, type?: string, jaar?: string, n: string}>
     */
    public function typeCounts(string $rdwBrand): array
    {
        $config = $this->datasetConfig('vehicles');
        $rows = [];

        for ($offset = 0; ; $offset += 50000) {
            $page = $this->get("/resource/{$config['id']}.json", [
                '$select' => 'handelsbenaming,type,date_extract_y(datum_eerste_toelating_dt) as jaar,count(*) as n',
                '$where' => "voertuigsoort = 'Personenauto' AND merk = '".str_replace("'", "''", $rdwBrand)."'",
                '$group' => 'handelsbenaming,type,jaar',
                '$order' => 'handelsbenaming,type,jaar',
                '$limit' => 50000,
                '$offset' => $offset,
            ]);

            array_push($rows, ...$page);

            if (count($page) < 50000) {
                return $rows;
            }
        }
    }

    /**
     * Average catalogue price per trade name and first-registration year for one brand (cars with a price only).
     *
     * @return array<int, array{handelsbenaming: string, jaar?: string, n: string, prijs: string}>
     */
    public function priceAverages(string $rdwBrand): array
    {
        $config = $this->datasetConfig('vehicles');

        return $this->get("/resource/{$config['id']}.json", [
            '$select' => 'handelsbenaming,date_extract_y(datum_eerste_toelating_dt) as jaar,count(catalogusprijs) as n,avg(catalogusprijs) as prijs',
            '$where' => "voertuigsoort = 'Personenauto' AND catalogusprijs > 0 AND merk = '".str_replace("'", "''", $rdwBrand)."'",
            '$group' => 'handelsbenaming,jaar',
            '$order' => 'handelsbenaming,jaar',
            '$limit' => 50000,
        ]);
    }

    /**
     * Look up one vehicle by license plate (letters and digits only, uppercase).
     * Uses a short timeout: a visitor is waiting for the answer.
     *
     * @return array<string, string>|null null when the plate does not exist
     */
    public function findVehicle(string $plate): ?array
    {
        $config = $this->datasetConfig('vehicles');

        $rows = $this->get("/resource/{$config['id']}.json", [
            '$select' => implode(',', ['kenteken', 'voertuigsoort', ...array_slice($config['columns'], 2)]),
            '$where' => "kenteken = '".str_replace("'", '', $plate)."'",
            '$limit' => 1,
        ], quick: true);

        return $rows[0] ?? null;
    }

    /**
     * Check that every column we need still exists in the dataset.
     *
     * @throws RdwSchemaChangedException
     */
    public function assertColumnsExist(string $dataset): void
    {
        $config = $this->datasetConfig($dataset);

        $view = $this->get("/api/views/{$config['id']}.json", []);
        $existing = array_column($view['columns'] ?? [], 'fieldName');

        $missing = array_diff(array_merge($config['columns'], $config['optional_columns']), $existing);

        if ($missing !== []) {
            $message = "RDW dataset '{$dataset}' ({$config['id']}) has changed: column(s) missing: ".implode(', ', $missing);
            Log::error($message);

            throw new RdwSchemaChangedException($message);
        }
    }

    private function where(array $config, array $extraWhere): ?string
    {
        $conditions = array_filter([$config['where'], ...$extraWhere]);

        return $conditions === [] ? null : implode(' AND ', $conditions);
    }

    private function datasetConfig(string $dataset): array
    {
        return config("rdw.datasets.{$dataset}")
            ?? throw new \InvalidArgumentException("Unknown RDW dataset '{$dataset}'");
    }

    private function request(bool $quick = false): PendingRequest
    {
        $request = Http::baseUrl(config('rdw.base_url'))
            ->timeout($quick ? 8 : config('rdw.timeout'))
            ->retry($quick ? 1 : 3, $quick ? 500 : 2000, throw: false)
            ->acceptJson();

        if ($token = config('rdw.app_token')) {
            $request = $request->withHeaders(['X-App-Token' => $token]);
        }

        return $request;
    }

    private function get(string $path, array $query, bool $quick = false): array
    {
        try {
            return $this->request($quick)->get($path, $query)->throw()->json() ?? [];
        } catch (ConnectionException|RequestException $e) {
            $message = "RDW API not reachable or returned an error for {$path}: ".$e->getMessage();
            Log::error($message);

            throw new RdwUnavailableException($message, previous: $e);
        }
    }
}
