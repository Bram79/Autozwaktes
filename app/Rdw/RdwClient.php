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

    private function request(): PendingRequest
    {
        $request = Http::baseUrl(config('rdw.base_url'))
            ->timeout(config('rdw.timeout'))
            ->retry(3, 2000, throw: false)
            ->acceptJson();

        if ($token = config('rdw.app_token')) {
            $request = $request->withHeaders(['X-App-Token' => $token]);
        }

        return $request;
    }

    private function get(string $path, array $query): array
    {
        try {
            return $this->request()->get($path, $query)->throw()->json() ?? [];
        } catch (ConnectionException|RequestException $e) {
            $message = "RDW API not reachable or returned an error for {$path}: ".$e->getMessage();
            Log::error($message);

            throw new RdwUnavailableException($message, previous: $e);
        }
    }
}
