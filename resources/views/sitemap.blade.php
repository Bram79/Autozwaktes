{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url><loc>{{ $url }}</loc>@if ($lastmod && ! in_array($url, $fixed, true))<lastmod>{{ $lastmod }}</lastmod>@endif</url>
@endforeach
</urlset>
