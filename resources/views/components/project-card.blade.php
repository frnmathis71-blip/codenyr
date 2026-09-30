@props(['project'])
<a class="project-card" href="{{ route('project', $project->slug) }}">
    <div class="project-visual">
    @if($project->imageUrl())<img src="{{ $project->imageUrl() }}" alt="Aperçu du projet {{ $project->name }}" loading="lazy" width="900" height="600">
    @else<div class="project-placeholder"><span class="eyebrow">{{ $project->category }}</span><strong>{{ $project->name }}</strong><span>Découvrir le projet <span class="mobile-decoration" aria-hidden="true">↗</span></span></div>@endif
    @if($project->is_demo)<span class="demo-label">Concept de démonstration</span>@endif
    </div>
    <div class="project-caption"><div><span class="eyebrow">{{ $project->category }}</span><h3>{{ $project->name }}</h3></div><span aria-hidden="true"><span class="mobile-decoration" aria-hidden="true">↗</span></span></div>
    <p>{{ $project->short_description }}</p>
</a>
