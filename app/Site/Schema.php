<?php

namespace App\Site;

/**
 * Structured data (schema.org) for Google, as plain arrays. Built in PHP, because in Blade an "@context" key would be read as a directive.
 * Only things that are true and that we can fill in from our own data: we do not claim a logo, a period or an author that we do not have.
 */
class Schema
{
    /** The site itself, with the search box: Google can show it in the results. */
    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('site.name'),
            'url' => url('/'),
            'inLanguage' => 'nl',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => url('/').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public static function organization(): array
    {
        return ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => config('site.name'), 'url' => url('/')];
    }

    /** The numbers of one generation or build year. The source is the RDW open data, which we calculated from. */
    public static function dataset(string $name, string $description, string $url, string $car): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Dataset',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'inLanguage' => 'nl',
            'keywords' => ['APK', 'APK-gebreken', $car, 'RDW open data'],
            'license' => 'https://creativecommons.org/publicdomain/zero/1.0/',
            'isBasedOn' => 'https://opendata.rdw.nl',
            'creator' => ['@type' => 'Organization', 'name' => config('site.name'), 'url' => url('/')],
        ];
    }

    /**
     * A list of pages, in order.
     *
     * @param  array<int, array{name: string, url: string}>  $items
     */
    public static function itemList(string $name, array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'numberOfItems' => count($items),
            'itemListElement' => array_map(
                fn (array $item, int $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $item['name'], 'url' => $item['url']],
                $items,
                array_keys($items),
            ),
        ];
    }
}
