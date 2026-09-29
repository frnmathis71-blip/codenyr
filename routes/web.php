<?php

use App\Http\Controllers\SiteController;
use App\Livewire\Admin\Leads;
use App\Livewire\Admin\Pricing;
use App\Livewire\Admin\Projects;
use App\Livewire\Admin\Testimonials;
use App\Livewire\CustomerReviews;
use App\Livewire\InquiryForm;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Testimonial;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::view('/services', 'site.services')->name('services');
Route::view('/tarifs', 'site.pricing')->name('pricing');
Route::get('/realisations', [SiteController::class, 'projects'])->name('projects');
Route::get('/avis', [SiteController::class, 'reviews'])->name('reviews');
Route::get('/realisations/{slug}', [SiteController::class, 'project'])->name('project');
Route::view('/a-propos', 'site.about')->name('about');
Route::get('/devis', InquiryForm::class)->name('quote');
Route::get('/contact', InquiryForm::class)->defaults('mode', 'contact')->name('contact');
Route::view('/mentions-legales', 'site.legal')->name('legal');
Route::view('/politique-confidentialite', 'site.privacy')->name('privacy');
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', fn () => response("User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /settings\nSitemap: ".url('/sitemap.xml'))->header('Content-Type', 'text/plain'));

Route::middleware(['auth', 'verified', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => view('admin.dashboard', [
        'counts' => collect(Lead::STATUSES)->map(fn () => 0)->merge(
            Lead::whereNull('archived_at')->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')
        ),
        'projectCount' => Project::count(),
        'pendingReviewCount' => Testimonial::where('moderation_status', 'pending')->count(),
        'leads' => Lead::whereNull('archived_at')->latest()->limit(8)->get(['firstname', 'lastname', 'company', 'project_type', 'status', 'created_at']),
    ]))->name('dashboard');
    Route::get('/prospects', Leads::class)->name('leads');
    Route::get('/archives', Leads::class)->defaults('archived', true)->name('archives');
    Route::get('/realisations', Projects::class)->name('projects');
    Route::get('/temoignages', Testimonials::class)->name('testimonials');
    Route::get('/tarifs', Pricing::class)->name('pricing');
});
Route::middleware(['auth', 'verified'])->get('/dashboard', fn () => redirect()->route(auth()->user()->is_admin ? 'admin.dashboard' : 'customer.dashboard'))->name('dashboard');
Route::middleware(['auth', 'verified'])->get('/espace-client', CustomerReviews::class)->name('customer.dashboard');
require __DIR__.'/settings.php';
