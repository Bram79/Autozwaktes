<?php

namespace App\Wikidata;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Small client for Wikidata (data under CC0): finds the item of a car series and its generations with start and end year.
 * Everything fails soft: when Wikidata does not answer, we return nothing and the proposal uses the RDW data alone.
 */
class WikidataClient
{
    /** The Wikidata item id of "Volkswagen Polo", or null when we are not sure which item it is. */
    public function seriesId(string $brand, string $model): ?string
    {
        $wanted = mb_strtolower("{$brand} {$model}");

        try {
            $results = $this->request()->get('https://www.wikidata.org/w/api.php', [
                'action' => 'wbsearchentities', 'search' => "{$brand} {$model}", 'language' => 'en', 'limit' => 5, 'format' => 'json',
            ])->throw()->json('search', []);
        } catch (Throwable $e) {
            Log::warning('Wikidata search failed: '.$e->getMessage());

            return null;
        }

        foreach ($results as $item) {
            $isCar = preg_match('/\b(car|automobile|vehicle|hatchback|suv|crossover|van|minivan|sedan|model|series)\b/i', $item['description'] ?? '');

            if (mb_strtolower($item['label'] ?? '') === $wanted && $isCar) {
                return $item['id'];
            }
        }

        return null;
    }

    /**
     * Generations of a series that have a start year.
     *
     * @param  string  $namePrefix  "Volkswagen Polo": removed from the label, so "Volkswagen Polo Mk5" becomes "Mk5"
     * @return array<int, array{short: string, start: int, end: int|null}> sorted by start year
     */
    public function generations(string $itemId, string $namePrefix): array
    {
        $query = <<<SPARQL
            SELECT ?label (MIN(?s) AS ?start) (MAX(?e) AS ?end) WHERE {
              ?gen wdt:P179 wd:{$itemId}; rdfs:label ?label.
              FILTER(LANG(?label) = "en")
              OPTIONAL { ?gen wdt:P580 ?s }
              OPTIONAL { ?gen wdt:P582 ?e }
            } GROUP BY ?label ORDER BY ?start
            SPARQL;

        try {
            $rows = $this->request()->get('https://query.wikidata.org/sparql', ['query' => $query])->throw()->json('results.bindings', []);
        } catch (Throwable $e) {
            Log::warning('Wikidata query failed: '.$e->getMessage());

            return [];
        }

        $generations = [];

        foreach ($rows as $row) {
            $label = $row['label']['value'];
            $short = trim(str_starts_with(mb_strtolower($label), mb_strtolower($namePrefix)) ? mb_substr($label, mb_strlen($namePrefix)) : $label);

            if (! isset($row['start']) || ! self::looksLikeGeneration($short)) {
                continue;   // no start year, or not a generation ("Playa", "Classic", "GTI R5")
            }

            $generations[] = [
                'short' => $short,
                'start' => (int) substr($row['start']['value'], 0, 4),
                'end' => isset($row['end']) ? (int) substr($row['end']['value'], 0, 4) : null,
            ];
        }

        return $generations;
    }

    /** "Mk5", "IV", "D", "B8", "3rd generation": yes. "Classic", "Variant": no. */
    public static function looksLikeGeneration(string $short): bool
    {
        return (bool) preg_match('/^(Mk\.? ?([IVX]+|\d+)|[IVX]{1,4}|[A-Z]\d?|\d(st|nd|rd|th) generation|Gen \d)$/i', $short);
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => config('site.name').'/1.0 (https://github.com/Bram79/Autozwaktes)',
            'Accept' => 'application/json',
        ])->timeout(30)->retry(2, 1000, throw: false);
    }
}
