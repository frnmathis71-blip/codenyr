<?php

namespace App\Livewire;

use App\Models\ClientProject;
use App\Models\Document;
use App\Models\Lead;
use App\Models\PrivacyRequest;
use App\Models\Testimonial;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Fortify\Features;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CustomerReviews extends Component
{
    #[Locked]
    public ?int $selected = null;

    public string $content = '';

    public int $rating = 5;

    public bool $consent = false;

    public string $privacyType = 'access';

    public string $privacyMessage = '';

    public function requestPrivacy(): void
    {
        $this->validate(['privacyType' => ['required', Rule::in(array_keys(PrivacyRequest::TYPES))], 'privacyMessage' => 'nullable|string|max:5000']);
        if (PrivacyRequest::where('user_id', Auth::id())->where('type', $this->privacyType)->whereNull('resolved_at')->exists()) {
            $this->addError('privacyType', 'Une demande de ce type est déjà en cours. Vous pouvez aussi contacter Codenyr par e-mail.');

            return;
        }
        PrivacyRequest::create(['user_id' => Auth::id(), 'email' => Auth::user()->email, 'type' => $this->privacyType, 'message' => $this->privacyMessage, 'due_at' => now()->addMonthNoOverflow()]);
        $this->privacyMessage = '';
        session()->flash('success', 'Demande enregistrée. Vous retrouverez la réponse dans cette rubrique ; le délai initial est d’un mois.');
    }

    public function withdrawReview(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $review = Testimonial::where('user_id', Auth::id())->lockForUpdate()->findOrFail($id);
            $review->update(['published' => false, 'moderation_status' => 'draft', 'withdrawn_at' => now(), 'revision' => $review->revision + 1]);
        });
        session()->flash('success', 'Votre avis a été retiré de la publication.');
    }

    public function boot(): void
    {
        abort_unless(Auth::check(), 403);
        if (Features::enabled(Features::emailVerification())) {
            abort_unless(Auth::user()->hasVerifiedEmail(), 403);
        }
    }

    private function eligibleLead(int $id): Lead
    {
        return Lead::where('user_id', Auth::id())->where('status', 'accepted')->whereNotNull('delivered_at')->findOrFail($id);
    }

    public function edit(int $id): void
    {
        $lead = $this->eligibleLead($id);
        $review = $lead->testimonial;
        abort_if($review && $review->user_id !== Auth::id(), 403);
        $this->selected = $lead->id;
        $this->content = $review->content ?? '';
        $this->rating = $review->rating ?? 5;
        $this->consent = false;
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->reset('selected', 'content', 'rating', 'consent');
        $this->resetValidation();
    }

    public function submit(): void
    {
        abort_unless($this->selected !== null, 404);
        $data = $this->validate([
            'content' => 'required|string|min:20|max:5000',
            'rating' => 'required|integer|between:1,5',
            'consent' => 'accepted',
        ], [
            'content.required' => 'Rédigez votre avis.',
            'content.min' => 'Votre avis doit contenir au moins 20 caractères.',
            'content.max' => 'Votre avis doit contenir au maximum 5 000 caractères.',
            'rating.between' => 'Choisissez une note entre 1 et 5.',
            'consent.accepted' => 'Votre accord est nécessaire pour publier votre avis.',
        ]);
        $key = 'customer-review:'.Auth::id();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('content', 'Trop d’envois. Merci de réessayer dans quelques minutes.');

            return;
        }
        DB::transaction(function () use ($data): void {
            // Serialize submissions per project, including its first review.
            $lead = Lead::where('user_id', Auth::id())->where('status', 'accepted')->whereNotNull('delivered_at')->lockForUpdate()->findOrFail($this->selected);
            $review = Testimonial::where('lead_id', $lead->id)->lockForUpdate()->first();
            abort_if($review && $review->user_id !== Auth::id(), 403);
            $revision = $review ? $review->revision + 1 : 1;
            $review ??= new Testimonial;
            $review->fill([
                'user_id' => Auth::id(), 'lead_id' => $lead->id,
                'client_name' => Auth::user()->name, 'company' => $lead->company,
                'content' => $data['content'], 'rating' => $data['rating'],
                'published' => false, 'moderation_status' => 'pending',
                'moderation_note' => null, 'revision' => $revision,
                'consented_at' => now(), 'consent_version' => 'review-publication-2026-10-01', 'withdrawn_at' => null,
            ])->save();
        });
        RateLimiter::hit($key, 600);
        $this->cancel();
        session()->flash('success', 'Merci ! Votre avis a été transmis à Codenyr. Il sera visible uniquement après validation.');
    }

    public function render(): View
    {
        return view('livewire.customer-reviews', [
            'privacyRequests' => PrivacyRequest::where('user_id', Auth::id())->latest()->get(),
            'myReviews' => Testimonial::where('user_id', Auth::id())->latest()->get(),
            'projects' => ClientProject::where('user_id', Auth::id())->latest()->get(['id', 'name', 'status', 'archived_at']),
            'documents' => Document::forCustomer((int) Auth::id())->orderByDesc('document_date')->get(['id', 'client_project_id', 'name', 'type', 'document_date']),
            'leads' => Lead::where('user_id', Auth::id())->with('testimonial')->latest()->get(),
        ])->layout('components.site-layout', ['title' => 'Mon espace client', 'description' => 'Retrouvez vos projets Codenyr et partagez votre expérience.']);
    }
}
