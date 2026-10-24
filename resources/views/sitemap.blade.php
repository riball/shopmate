<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as [$loc, $updated])
    <url>
        <loc>{{ $loc }}</loc>
@if ($updated)
        <lastmod>{{ $updated->toDateString() }}</lastmod>
@endif
    </url>
@endforeach
</urlset>