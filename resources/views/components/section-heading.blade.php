@props(['number', 'label', 'title', 'text' => null])
<div class="section-heading"><div><p class="eyebrow"><span>{{ $number }} /</span> {{ $label }}</p><h2>{{ $title }}</h2></div>@if($text)<p class="section-intro">{{ $text }}</p>@endif</div>
