{{--@formatter:off--}}
@if($hreflang)
{{$hreflang->render()}}
@endif
@if($title)
<title>{{ $title }}</title>
@endif
@if($description)
<meta name="description" content="{{ $description }}">
@endif
@if($keywords)
<meta name="keywords" content="{{ $keywords }}">
@endif
@if($robots)
<meta name="robots" content="{{ $robots }}">
@endif
@if($canonicalUrl)
<link rel="canonical" href="{{ $canonicalUrl }}">
@endif
@if($openGraph)
{!! $openGraph !!}
@endif
@if($twitter)
{!! $twitter !!}
@endif
@if($schema)
{!! $schema->toScript() !!}
@endif
