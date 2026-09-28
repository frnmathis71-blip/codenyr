<?php

namespace App\Livewire;

use App\Models\Lead;
use App\Models\Testimonial;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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
            ])->save();
        });
        RateLimiter::hit($key, 600);
        $this->cancel();
        session()->flash('success', 'Merci ! Votre avis a été transmis à Codenyr. Il sera visible uniquement après validation.');
    }

    public function render(): View
    {
        return view('livewire.customer-reviews', [
            'leads' => Lead::where('user_id', Auth::id())->with('testimonial')->latest()->get(),
        ])->layout('components.site-layout', ['title' => 'Mon espace client', 'description' => 'Retrouvez vos projets Codenyr et partagez votre expérience.']);
    }
}
