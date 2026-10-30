<?php

namespace App\Site;

use App\Models\Generation;

/**
 * Writes a short text about a generation (or one build year) from our own statistics.
 * Every number in the text comes straight from the database; the wording changes per page (but is the same on every visit).
 */
class AdviceWriter
{
    public function __construct(private StatsService $stats) {}

    /**
     * The numbers behind the text. This is also all the AI gets to see.
     *
     * @return array<string, mixed>|null null when there are too few cars for reliable percentages
     */
    public function facts(Generation $generation, ?int $year = null): ?array
    {
        $generation->loadMissing('carModel.brand');
        $top = $this->stats->topDefects($generation, $year);

        if (! $top['enough_data']) {
            return null;
        }

        $comparison = $this->stats->comparison($generation, $year);
        $price = $this->stats->newPrice($generation, $year);

        return [
            'auto' => Seo::generationName($generation),
            'bouwjaren' => Seo::yearsLabel($generation),
            'bouwjaar' => $year,
            'aantal_voertuigen' => $top['total'],
            'meest_voorkomend' => $top['defects']->take(3)->map(fn ($d) => [
                'gebrek' => $d->description, 'percentage' => $d->percentage, 'gemiddeld_percentage' => $d->average,
            ])->values()->all(),
            'vaker_dan_gemiddeld' => array_map(fn ($p) => $this->point($p), $comparison['weak'] ?? []),
            'minder_vaak_dan_gemiddeld' => array_map(fn ($p) => $this->point($p), $comparison['strong'] ?? []),
            'nieuwprijs_gemiddeld_euro' => $price['average'] ?? null,
        ];
    }

    /** @return string[] paragraphs of plain text (no HTML) */
    public function paragraphs(Generation $generation, ?int $year = null): array
    {
        $facts = $this->facts($generation, $year);

        if ($facts === null) {
            return [];
        }

        $seed = fn (string $slot, array $variants) => $variants[crc32("{$generation->id}-{$year}-{$slot}") % count($variants)];
        $auto = $facts['auto'];
        $of = $year ? "uit {$year}" : '('.$facts['bouwjaren'].')';
        $total = $this->number($facts['aantal_voertuigen']);

        $paragraphs = [];

        $paragraphs[] = $seed('intro', [
            "De cijfers over de {$auto} {$of} komen van {$total} voertuigen die bij de RDW geregistreerd staan.",
            "In dit overzicht zitten {$total} voertuigen van de {$auto} {$of}, gevolgd via de APK-gegevens van de RDW.",
            "We hebben {$total} geregistreerde {$auto} {$of} gevolgd in de APK-gegevens van de RDW.",
        ]);

        $top = $facts['meest_voorkomend'];
        $first = $top[0];
        $line = $seed('top', [
            "Het vaakst wordt {$this->lower($first['gebrek'])} geconstateerd: bij {$this->percent($first['percentage'])} van de auto's.",
            "Bovenaan staat {$this->lower($first['gebrek'])}, bij {$this->percent($first['percentage'])} van de auto's.",
            "{$this->upper($first['gebrek'])} komt het meest voor ({$this->percent($first['percentage'])} van de auto's).",
        ]);
        if (count($top) > 1) {
            $rest = array_map(fn ($d) => "{$this->lower($d['gebrek'])} ({$this->percent($d['percentage'])})", array_slice($top, 1));
            $line .= ' '.$seed('top-next', ['Daarna volgen', 'Op de volgende plaatsen staan', 'Hierna komen'])." {$this->join($rest)}.";
        }
        $paragraphs[] = $line;

        if ($facts['vaker_dan_gemiddeld'] !== []) {
            $items = array_map(fn ($p) => "{$this->lower($p['gebrek'])} ({$this->times($p['keer_vaker'])} vaker: {$this->percent($p['percentage'])} tegenover {$this->percent($p['gemiddeld_percentage'])})", $facts['vaker_dan_gemiddeld']);
            $paragraphs[] = $seed('weak', [
                ($year ? "Let bij bouwjaar {$year} vooral op " : 'Let vooral op ').$this->join($items).'.',
                "Hier scoort de {$auto} slechter dan andere modellen: ".$this->join($items).'.',
                'Duidelijk vaker dan gemiddeld wordt geconstateerd: '.$this->join($items).'.',
            ]);
        } else {
            $paragraphs[] = $seed('no-weak', [
                'Geen enkel veelvoorkomend gebrek komt duidelijk vaker voor dan bij andere modellen.',
                "Bij de gebreken die vaak voorkomen, wijkt de {$auto} niet sterk negatief af van het gemiddelde.",
            ]);
        }

        if ($facts['minder_vaak_dan_gemiddeld'] !== []) {
            $items = array_map(fn ($p) => "{$this->lower($p['gebrek'])} ({$this->percent($p['percentage'])} tegenover {$this->percent($p['gemiddeld_percentage'])})", $facts['minder_vaak_dan_gemiddeld']);
            $paragraphs[] = $seed('strong', [
                "Beter dan gemiddeld scoort de {$auto} op ".$this->join($items).'.',
                'Minder vaak dan bij andere modellen wordt geconstateerd: '.$this->join($items).'.',
                'Een positief punt: '.$this->join($items).'.',
            ]);
        }

        if ($facts['nieuwprijs_gemiddeld_euro']) {
            $price = '€ '.$this->number($facts['nieuwprijs_gemiddeld_euro']);
            $paragraphs[] = $seed('price', [
                "De gemiddelde nieuwprijs (catalogusprijs volgens de RDW) was {$price}.",
                "Volgens de RDW was de catalogusprijs, dus de nieuwprijs, gemiddeld {$price}.",
            ]);
        }

        return $paragraphs;
    }

    private function point(array $p): array
    {
        return ['gebrek' => $p['description'], 'percentage' => $p['percentage'], 'gemiddeld_percentage' => $p['average'], 'keer_vaker' => $p['ratio']];
    }

    private function number(int|float $n): string
    {
        return number_format($n, 0, ',', '.');
    }

    private function percent(float $p): string
    {
        return number_format($p, 1, ',', '.').'%';
    }

    private function times(float $ratio): string
    {
        return number_format($ratio, 1, ',', '.').' keer';
    }

    private function lower(string $text): string
    {
        return mb_strtolower(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    private function upper(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    /** "a, b en c" */
    private function join(array $items): string
    {
        $last = array_pop($items);

        return $items === [] ? $last : implode(', ', $items).' en '.$last;
    }
}
