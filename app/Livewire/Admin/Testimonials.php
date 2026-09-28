<?php

namespace App\Livewire\Admin;

use App\Models\Lead;
use App\Models\Testimonial;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

class Testimonials extends AdminComponent
{
    use WithPagination;

    #[Locked]
    public ?int $editing = null;

    public bool $showForm = false;

    public string $filter = '';

    public string $moderation_note = '';

    #[Locked]
    public int $reviewedRevision = 0;

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    /** @var array<string, mixed> */
    public array $form = [];

    public function create(): void
    {
        $this->editing = null;
        $this->moderation_note = '';
        $this->reviewedRevision = 0;
        $this->form = ['client_name' => '', 'company' => '', 'content' => '', 'rating' => 5, 'published' => false];
        $this->showForm = true;
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $this->create();
        $testimonial = Testimonial::findOrFail($id);
        $this->editing = $testimonial->id;
        $this->reviewedRevision = $testimonial->revision;
        $this->moderation_note = $testimonial->moderation_note ?? '';
        $this->form = $testimonial->only(array_keys($this->form));
    }

    public function save(): void
    {
        if ($this->editing && Testimonial::findOrFail($this->editing)->user_id !== null) {
            abort(403, 'Les avis clients doivent être approuvés ou refusés sans modifier leur contenu.');
        }
        $data = $this->validate(['form.client_name' => 'required|string|max:200', 'form.company' => 'nullable|string|max:200', 'form.content' => 'required|string|max:5000', 'form.rating' => 'required|integer|between:1,5', 'form.published' => 'required|boolean']);
        $testimonial = $this->editing ? Testimonial::findOrFail($this->editing) : new Testimonial;
        $data['form']['moderation_status'] = $data['form']['published'] ? 'approved' : 'draft';
        $testimonial->fill($data['form'])->save();
        $this->showForm = false;
        session()->flash('success', 'Témoignage enregistré.');
    }

    public function moderate(string $decision): void
    {
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);
        $this->validate(['moderation_note' => $decision === 'rejected' ? 'required|string|max:2000' : 'nullable|string|max:2000'], ['moderation_note.required' => 'Indiquez au client pourquoi son avis doit être revu.']);
        DB::transaction(function () use ($decision): void {
            $leadId = Testimonial::findOrFail($this->editing)->lead_id;
            $lead = $leadId ? Lead::lockForUpdate()->find($leadId) : null;
            $review = Testimonial::lockForUpdate()->findOrFail($this->editing);
            abort_unless($review->user_id !== null, 403);
            if ($review->revision !== $this->reviewedRevision) {
                throw ValidationException::withMessages(['moderation_note' => 'Le client a modifié cet avis. Fermez puis rouvrez-le pour lire sa nouvelle version avant de le modérer.']);
            }
            if ($decision === 'approved' && (! $lead || $lead->status !== 'accepted' || ! $lead->delivered_at || $lead->user_id !== $review->user_id)) {
                throw ValidationException::withMessages(['moderation_note' => 'La publication nécessite un devis accepté, un site livré et le bon compte client associé.']);
            }
            $review->update(['moderation_status' => $decision, 'published' => $decision === 'approved', 'moderation_note' => $this->moderation_note ?: null]);
        });
        $this->showForm = false;
        session()->flash('success', $decision === 'approved' ? 'Avis approuvé et publié sur le site.' : 'Avis refusé. Le client peut consulter votre retour et le modifier.');
    }

    public function delete(int $id): void
    {
        Testimonial::findOrFail($id)->delete();
        $this->showForm = false;
        session()->flash('success', 'Témoignage supprimé.');
    }

    public function render(): View
    {
        return view('livewire.admin.testimonials', [
            'testimonials' => Testimonial::when($this->filter !== '', fn ($query) => $query->where('moderation_status', $this->filter))->orderByRaw("CASE WHEN moderation_status = 'pending' THEN 0 ELSE 1 END")->latest()->paginate(15),
            'review' => $this->editing ? Testimonial::with('lead')->find($this->editing) : null,
            'pendingCount' => Testimonial::where('moderation_status', 'pending')->count(),
        ])->layout('components.admin-layout', ['title' => 'Témoignages']);
    }
}
