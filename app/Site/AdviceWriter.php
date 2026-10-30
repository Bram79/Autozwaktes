<?php

namespace App\Site;

use App\Models\Generation;

/**
 * Turns our own statistics for a generation (or one build year) into the facts and the table on the page.
 * Every number comes straight from the database.
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

    /**
     * The numbers as table rows for the page ("Nieuwprijs | € 47.254"). Every number comes straight from the facts.
     *
     * @return array<int, array{label: string, lines: string[], tone: string|null}> tone: good, bad or null
     */
    public function rows(Generation $generation, ?int $year = null): array
    {
        $facts = $this->facts($generation, $year);

        if ($facts === null) {
            return [];
        }

        $rows = [];

        if ($facts['nieuwprijs_gemiddeld_euro']) {
            $rows[] = ['label' => 'Nieuwprijs', 'lines' => ['€ '.$this->number($facts['nieuwprijs_gemiddeld_euro']).' (gemiddelde catalogusprijs volgens de RDW)'], 'tone' => null];
        }

        $rows[] = ['label' => 'Voertuigen', 'lines' => [$this->number($facts['aantal_voertuigen']).' geregistreerd bij de RDW'], 'tone' => null];

        $rows[] = [
            'label' => 'Vaakst voorkomend',
            'lines' => array_map(fn ($d) => $this->upper($d['gebrek']).': '.$this->percent($d['percentage']), $facts['meest_voorkomend']),
            'tone' => null,
        ];

        $rows[] = [
            'label' => 'Vaker dan gemiddeld',
            'lines' => $facts['vaker_dan_gemiddeld'] === []
                ? ['Geen enkel veelvoorkomend gebrek valt duidelijk negatief op.']
                : array_map(fn ($p) => $this->upper($p['gebrek']).': '.$this->times($p['keer_vaker']).' vaker ('.$this->percent($p['percentage']).' tegenover '.$this->percent($p['gemiddeld_percentage']).')', $facts['vaker_dan_gemiddeld']),
            'tone' => $facts['vaker_dan_gemiddeld'] === [] ? null : 'bad',
        ];

        if ($facts['minder_vaak_dan_gemiddeld'] !== []) {
            $rows[] = [
                'label' => 'Beter dan gemiddeld',
                'lines' => array_map(fn ($p) => $this->upper($p['gebrek']).': '.$this->percent($p['percentage']).' tegenover '.$this->percent($p['gemiddeld_percentage']), $facts['minder_vaak_dan_gemiddeld']),
                'tone' => 'good',
            ];
        }

        return $rows;
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

    private function upper(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }
}
