<?php

use App\Http\Controllers\CommercialDocumentController;
use App\Http\Controllers\CustomerDocumentController;
use App\Http\Controllers\SiteController;
use App\Livewire\Admin\Billing;
use App\Livewire\Admin\ClientProjects;
use App\Livewire\Admin\CommercialDirectory;
use App\Livewire\Admin\CommercialDocuments;
use App\Livewire\Admin\CommercialSearch;
use App\Livewire\Admin\Leads;
use App\Livewire\Admin\Pricing;
use App\Livewire\Admin\PrivacyRequests;
use App\Livewire\Admin\ProjectDossier;
use App\Livewire\Admin\Projects;
use App\Livewire\Admin\Testimonials;
use App\Livewire\CustomerReviews;
use App\Livewire\InquiryForm;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Testimonial;
use App\Services\CommercialOverview;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::view('/services', 'site.services')->name('services');
Route::view('/demonstrations/sillage', 'demos.sillage')->name('demo.landing');
Route::get('/demonstrations/atelier-rive/{page?}', function (string $page = 'accueil') {
    abort_unless(in_array($page, ['accueil', 'atelier', 'expertises', 'realisations', 'contact']), 404);

    return view('demos.atelier', ['page' => $page]);
})->name('demo.vitrine');
Route::get('/demonstrations/canopee/{page?}', function (string $page = 'accueil') {
    abort_unless(in_array($page, ['accueil', 'studio', 'realisations', 'journal', 'gestion', 'connexion']), 404);

    return view('demos.canopee', ['page' => $page]);
})->name('demo.pro');
Route::view('/tarifs', 'site.pricing')->name('pricing');
Route::get('/realisations', [SiteController::class, 'projects'])->name('projects');
Route::get('/avis', [SiteController::class, 'reviews'])->name('reviews');
Route::get('/realisations/{slug}', [SiteController::class, 'project'])->name('project');
Route::view('/a-propos', 'site.about')->name('about');
Route::get('/devis', InquiryForm::class)->name('quote');
Route::get('/contact', InquiryForm::class)->defaults('mode', 'contact')->name('contact');
Route::view('/mentions-legales', 'site.legal')->name('legal');
Route::view('/politique-confidentialite', 'site.privacy')->name('privacy');
Route::view('/cgu', 'site.terms')->name('terms');
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', fn () => response("User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /settings\nSitemap: ".url('/sitemap.xml'))->header('Content-Type', 'text/plain'));

Route::middleware(['auth', 'verified', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => view('admin.dashboard', [
        'counts' => collect(Lead::STATUSES)->map(fn () => 0)->merge(
            Lead::whereNull('archived_at')->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')
        ),
        'projectCount' => Project::count(),
        'pendingReviewCount' => Testimonial::where('moderation_status', 'pending')->count(),
        'commercial' => app(CommercialOverview::class)->data(),
        'leads' => Lead::whereNull('archived_at')->latest()->limit(8)->get(['firstname', 'lastname', 'company', 'project_type', 'status', 'created_at']),
    ]))->name('dashboard');
    Route::get('/prospects', Leads::class)->name('leads');
    Route::get('/archives', Leads::class)->defaults('archived', true)->name('archives');
    Route::get('/realisations', Projects::class)->name('projects');
    Route::get('/temoignages', Testimonials::class)->name('testimonials');
    Route::get('/tarifs', Pricing::class)->name('pricing');
    Route::get('/projets', ClientProjects::class)->name('client-projects.index');
    Route::get('/projets/{clientProject}', ProjectDossier::class)->name('client-projects.show');
    Route::get('/clients', CommercialDirectory::class)->defaults('module', 'clients')->name('clients');
    Route::get('/demandes-rgpd', PrivacyRequests::class)->name('privacy-requests');
    Route::get('/catalogue', CommercialDirectory::class)->defaults('module', 'catalog')->name('catalog');
    Route::get('/parametres-commerciaux', CommercialDirectory::class)->defaults('module', 'settings')->name('commercial-settings');
    Route::get('/modeles-documents', CommercialDirectory::class)->defaults('module', 'templates')->name('document-templates');
    Route::get('/documents', CommercialDocuments::class)->name('documents');
    Route::get('/documents/{document}/telecharger', [CommercialDocumentController::class, 'download'])->name('documents.download');
    Route::get('/documents/{document}/apercu', [CommercialDocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/versions/{version}/telecharger', [CommercialDocumentController::class, 'version'])->name('documents.version');
    Route::get('/recherche', CommercialSearch::class)->name('commercial-search');
    // Keep public /devis and the existing admin.projects portfolio route intact.
    foreach (['devis' => 'quotes', 'factures' => 'invoices'] as $path => $kind) {
        Route::get('/'.$path, Billing::class)->defaults('kind', $kind)->name('billing.'.$kind.'.index');
        Route::get('/'.$path.'/nouveau', Billing::class)->defaults('kind', $kind)->name('billing.'.$kind.'.create');
        Route::get('/'.$path.'/{id}', Billing::class)->defaults('kind', $kind)->whereNumber('id')->name('billing.'.$kind.'.edit');
    }
    Route::get('/commercial/{kind}', fn (string $kind) => redirect()->route('admin.billing.'.$kind.'.index'))->whereIn('kind', ['quotes', 'invoices'])->name('billing.index');
    Route::get('/commercial/{kind}/nouveau', fn (string $kind) => redirect()->route('admin.billing.'.$kind.'.create', request()->query()))->whereIn('kind', ['quotes', 'invoices'])->name('billing.create');
    Route::get('/commercial/{kind}/{id}', fn (string $kind, int $id) => redirect()->route('admin.billing.'.$kind.'.edit', ['id' => $id]))->whereIn('kind', ['quotes', 'invoices'])->whereNumber('id')->name('billing.edit');
});
Route::middleware(['auth', 'verified'])->get('/dashboard', fn () => redirect()->route(auth()->user()->is_admin ? 'admin.dashboard' : 'customer.dashboard'))->name('dashboard');
Route::middleware(['auth', 'verified'])->get('/espace-client', CustomerReviews::class)->name('customer.dashboard');
Route::middleware(['auth', 'verified'])->get('/espace-client/documents/{document}/telecharger', [CustomerDocumentController::class, 'download'])->whereNumber('document')->name('customer.documents.download');
require __DIR__.'/settings.php';
