<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Testimonial;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(): View
    {
        return view('site.home', ['projects' => Project::where('published', true)->orderByDesc('featured')->latest()->limit(3)->get(), 'testimonials' => Testimonial::where('published', true)->latest()->limit(3)->get()]);
    }

    public function projects(): View
    {
        return view('site.projects', ['projects' => Project::where('published', true)->orderByDesc('featured')->latest()->paginate(9)]);
    }

    public function project(string $slug): View
    {
        return view('site.project', ['project' => Project::where('published', true)->where('slug', $slug)->firstOrFail()]);
    }

    public function sitemap(): Response
    {
        $paths = ['/', '/services', '/tarifs', '/realisations', '/a-propos', '/devis', '/contact', '/mentions-legales', '/politique-confidentialite'];
        $projects = Project::where('published', true)->get(['slug', 'updated_at']);

        return response()->view('site.sitemap', compact('paths', 'projects'))->header('Content-Type', 'application/xml');
    }
}
