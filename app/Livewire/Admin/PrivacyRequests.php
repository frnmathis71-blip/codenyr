<?php

namespace App\Livewire\Admin;

use App\Models\PrivacyRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\WithPagination;

class PrivacyRequests extends AdminComponent
{
    use WithPagination;

    /** @var array<int, string> */
    public array $responses = [];

    public function resolve(int $id): void
    {
        $this->validate(['responses.'.$id => 'required|string|min:10|max:10000'], ['responses.'.$id.'.required' => 'Indiquez les opérations effectuées et la réponse destinée au client.']);
        DB::transaction(function () use ($id): void {
            $request = PrivacyRequest::lockForUpdate()->findOrFail($id);
            if ($request->resolved_at) {
                throw ValidationException::withMessages(['privacy' => 'Cette demande a déjà reçu une réponse.']);
            }
            $request->update(['response' => $this->responses[$id], 'resolved_at' => now()]);
        });
        unset($this->responses[$id]);
        session()->flash('success', 'Réponse conservée et disponible dans l’espace client. Aucun e-mail automatique n’a été envoyé.');
    }

    public function render(): View
    {
        return view('livewire.admin.privacy-requests', ['requests' => PrivacyRequest::orderByRaw('resolved_at IS NOT NULL')->orderBy('due_at')->paginate(20)])
            ->layout('components.admin-layout', ['title' => 'Demandes RGPD']);
    }
}
