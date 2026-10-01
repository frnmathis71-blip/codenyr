@props(['testimonial'])
<figure class="public-review-card">
    <figcaption class="public-review-author">
        <span class="public-review-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($testimonial->client_name, 0, 1)) }}</span>
        <div><strong>{{ $testimonial->client_name }}</strong>@if($testimonial->company)<span>{{ $testimonial->company }}</span>@endif</div>
    </figcaption>
    <div class="public-review-rating" aria-label="Note : {{ $testimonial->rating }} sur 5">
        <span aria-hidden="true">@foreach(range(1,5) as $star)<span @class(['review-star-empty' => $star > $testimonial->rating])><x-ui-icon :name="$star <= $testimonial->rating ? 'star' : 'star-outline'" /></span>@endforeach</span>
        <span aria-hidden="true">{{ $testimonial->rating }} / 5</span>
    </div>
    <blockquote>{{ $testimonial->content }}</blockquote>
</figure>
