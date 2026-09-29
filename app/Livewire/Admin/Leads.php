<?php

namespace App\Livewire\Admin;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Fortify\Features;
use Livewire\Attributes\Locked;
use Livewire\WithPagination;

class Leads extends AdminComponent
{
    use WithPagination;

    public string $search = '';

    public string $filter = '';

    #[Locked]
    public ?int $selected = null;

    public string $status = 'new';

    public string $notes = '';

    public string $client_email = '';

    public bool $delivered = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function open(int $id): void
    {
        $lead = Lead::findOrFail($id);
        $this->selected = $lead->id;
        $this->status = $lead->status;
        $this->notes = $lead->notes ?? '';
        $this->client_email = $lead->client->email ?? '';
        $this->delivered = $lead->delivered_at !== null;
        $this->resetValidation();
    }

    public function close(): void
    {
        $this->selected = null;
    }

    public function save(): void
    {
        $this->validate(['status' => ['required', Rule::in(array_keys(Lead::STATUSES))], 'notes' => 'nullable|string|max:20000', 'client_email' => 'nullable|email|max:255', 'delivered' => 'boolean']);
        $verificationRequired = Features::enabled(Features::emailVerification());
        $client = $this->client_email !== '' ? User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($this->client_email))])->where('is_admin', false)->when($verificationRequired, fn ($query) => $query->whereNotNull('email_verified_at'))->first() : null;
        if ($this->client_email !== '' && ! $client) {
            $this->addError('client_email', $verificationRequired ? 'Aucun compte client vérifié ne correspond. Le client doit d’abord s’inscrire et confirmer son e-mail.' : 'Aucun compte client ne correspond. Le client doit d’abord s’inscrire.');

            return;
        }
        if ($this->delivered && $this->status !== 'accepted') {
            $this->addError('delivered', 'Le devis doit être accepté pour confirmer la livraison du site.');

            return;
        }
        DB::transaction(function () use ($client): void {
            $lead = Lead::lockForUpdate()->findOrFail($this->selected);
            if ($lead->testimonial()->exists() && $lead->user_id !== $client?->id) {
                throw ValidationException::withMessages(['client_email' => 'Un avis est déjà lié à ce client. Supprimez cet avis avant de réattribuer le projet.']);
            }
            $lead->update(['status' => $this->status, 'notes' => $this->notes, 'user_id' => $client?->id, 'delivered_at' => $this->delivered ? ($lead->delivered_at ?? now()) : null]);
            if ($this->status !== 'accepted' || ! $this->delivered) {
                $lead->testimonial()->whereNotNull('user_id')->where('published', true)->update(['published' => false, 'moderation_status' => 'pending']);
            }
        });
        session()->flash('success', 'Prospect mis à jour.');
    }

    public function delete(int $id): void
    {
        Lead::findOrFail($id)->delete();
        $this->selected = null;
        session()->flash('success', 'Prospect supprimé.');
    }

    public function render(): View
    {
        $leads = Lead::query()->when($this->search !== '', fn ($q) => $q->where(function ($q) {
            $term = '%'.mb_substr($this->search, 0, 200).'%';
            $q->where('firstname', 'like', $term)->orWhere('lastname', 'like', $term)->orWhere('email', 'like', $term)->orWhere('company', 'like', $term);
        }))->when($this->filter !== '', fn ($q) => $q->where('status', $this->filter))->latest()->paginate(15, ['id', 'firstname', 'lastname', 'company', 'email', 'project_type', 'status', 'created_at']);

        return view('livewire.admin.leads', ['leads' => $leads, 'lead' => $this->selected ? Lead::find($this->selected) : null])->layout('components.admin-layout', ['title' => 'Prospects']);
    }
}
