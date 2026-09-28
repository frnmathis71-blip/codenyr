{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($paths as $path)<url><loc>{{ url($path) }}</loc></url>@endforeach
@foreach($projects as $project)<url><loc>{{ route('project', $project->slug) }}</loc><lastmod>{{ $project->updated_at->toAtomString() }}</lastmod></url>@endforeach
</urlset>
