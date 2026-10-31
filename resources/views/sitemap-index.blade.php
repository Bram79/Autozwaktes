{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($chunks as $number)
    <sitemap><loc>{{ url("/sitemap-{$number}.xml") }}</loc>@if ($lastmod)<lastmod>{{ $lastmod }}</lastmod>@endif</sitemap>
@endforeach
</sitemapindex>
