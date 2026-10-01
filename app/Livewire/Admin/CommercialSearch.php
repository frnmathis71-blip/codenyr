<?php

namespace App\Livewire\Admin;

use App\Models\Client;
use App\Models\ClientProject;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Quote;
use Illuminate\View\View;

class CommercialSearch extends AdminComponent
{
    public string $search = '';

    public function render(): View
    {
        $term = '%'.mb_substr(trim($this->search), 0, 200).'%';
        $enabled = mb_strlen(trim($this->search)) >= 2;
        $projects = ClientProject::with('client')->where(fn ($q) => $q->where('name', 'like', $term)->orWhereHas('client', fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)))->limit(20)->get();
        $projectMatch = fn ($q) => $q->where('name', 'like', $term)->orWhereHas('client', fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));

        return view('livewire.admin.commercial-search', [
            'enabled' => $enabled, 'clients' => $enabled ? Client::where('name', 'like', $term)->orWhere('email', 'like', $term)->limit(20)->get() : collect(), 'projects' => $enabled ? $projects : collect(),
            'quotes' => $enabled ? Quote::where('number', 'like', $term)->orWhereHas('project', $projectMatch)->limit(20)->get() : collect(),
            'invoices' => $enabled ? Invoice::where('number', 'like', $term)->orWhereHas('project', $projectMatch)->limit(20)->get() : collect(),
            'documents' => $enabled ? Document::where('name', 'like', $term)->orWhereHas('project', $projectMatch)->orWhereHas('client', fn ($q) => $q->where('name', 'like', $term))->limit(20)->get() : collect(),
        ])->layout('components.admin-layout', ['title' => 'Recherche']);
    }
}
