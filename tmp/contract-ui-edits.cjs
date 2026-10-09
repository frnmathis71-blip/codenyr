const fs=require('fs');function edit(p,a,b){let s=fs.readFileSync(p,'utf8');if(!s.includes(a))throw Error(p+' '+a);fs.writeFileSync(p,s.replace(a,b));}
edit('app/Livewire/Admin/CommercialDirectory.php',"public array $form = [];","public array $form = [];\n\n    public array $contractRules = [];");
edit('app/Livewire/Admin/CommercialDirectory.php',"$this->editing = null;","$this->editing = null;\n        $this->contractRules = \\App\\Services\\ContractCatalogRules::editor([]);");
edit('app/Livewire/Admin/CommercialDirectory.php',"if ($record instanceof Service) {","if ($record instanceof Service) {\n            $this->contractRules = \\App\\Services\\ContractCatalogRules::editor($record->contract_rules ?? []);");
edit('app/Livewire/Admin/CommercialDirectory.php',"$logoPath = null;","if ($this->module === 'catalog') {\n            $data['contract_rules'] = \\App\\Services\\ContractCatalogRules::fromEditor($this->contractRules, $this->editing ? (Service::findOrFail($this->editing)->contract_rules ?? []) : []);\n        }\n        $logoPath = null;");
edit('resources/views/livewire/admin/commercial-directory.blade.php','<div class="field"><label for="pricing-mode">',"@include('livewire.admin.contract-catalog-fields', ['prefix'=>'catalog-contract', 'binding'=>'contractRules'])\n<div class=\"field\"><label for=\"pricing-mode\">");
edit('app/Livewire/Admin/Billing.php',"public array $items = [];","public array $items = [];\n\n    public array $ruleEditors = [];");
edit('app/Livewire/Admin/Billing.php',"$this->selectedServices = array_values(array_unique(array_filter(array_column($this->items, 'service_id'))));","$this->ruleEditors = array_map(fn ($item) => \\App\\Services\\ContractCatalogRules::editor($item['contract_rules'] ?? []), $this->items);\n        $this->selectedServices = array_values(array_unique(array_filter(array_column($this->items, 'service_id'))));");
edit('app/Livewire/Admin/Billing.php',"$this->items[] = ['contract_rules'", "$this->ruleEditors[] = \\App\\Services\\ContractCatalogRules::editor([]);\n        $this->items[] = ['contract_rules'");
edit('app/Livewire/Admin/Billing.php',"unset($this->items[$index]);","unset($this->items[$index], $this->ruleEditors[$index]);\n        $this->ruleEditors = array_values($this->ruleEditors);");
// Re-index rule editors by retaining the matching item when toggling catalog entries.
edit('app/Livewire/Admin/Billing.php',"$service = Service::where('active'", "$this->syncRuleEditors();\n        $service = Service::where('active'");
edit('app/Livewire/Admin/Billing.php',"            return;\n        }\n        if ($this->kind === 'quotes'", "            $this->ruleEditors = array_map(fn ($item) => \\App\\Services\\ContractCatalogRules::editor($item['contract_rules'] ?? []), $this->items);\n            return;\n        }\n        if ($this->kind === 'quotes'");
edit('app/Livewire/Admin/Billing.php',"        $this->selectedServices[] = $id;", "        $this->selectedServices[] = $id;\n        $this->ruleEditors = array_map(fn ($item) => \\App\\Services\\ContractCatalogRules::editor($item['contract_rules'] ?? []), $this->items);\n        if (! filled($this->form['estimated_delay'] ?? '') && filled($service->contract_rules['delivery_days'] ?? null)) {\n            $this->form['estimated_delay'] = $service->contract_rules['delivery_days'].' jours';\n        }");
edit('app/Livewire/Admin/Billing.php',"    public function updatedOfferChoice(): void",`    private function syncRuleEditors(): void
    {
        foreach ($this->items as $index => &$item) {
            if (isset($this->ruleEditors[$index])) {
                $item['contract_rules'] = \\App\\Services\\ContractCatalogRules::fromEditor($this->ruleEditors[$index], $item['contract_rules'] ?? []);
            }
        }
        unset($item);
    }

    public function updatedOfferChoice(): void`);
edit('app/Livewire/Admin/Billing.php',"$calculator = app(CommercialCalculator::class);", "$this->syncRuleEditors();\n        $calculator = app(CommercialCalculator::class);");
edit('resources/views/livewire/admin/billing.blade.php',"@if($kind==='quotes')<label class=\"commercial-checkbox\">", "@if($kind==='quotes')\n@include('livewire.admin.contract-catalog-fields', ['prefix'=>'line-contract-'.$index, 'binding'=>'ruleEditors.'.$index])\n<label class=\"commercial-checkbox\"><input type=\"checkbox\" wire:model=\"items.{{ $index }}.contract_included\"> Incluse au contrat</label>\n<label class=\"commercial-checkbox\">");
edit('resources/views/livewire/admin/billing.blade.php','+ Ligne personnalisée','+ Ajouter une fonctionnalité personnalisée');
edit('resources/views/livewire/admin/legal-document-fields.blade.php',"$legalForm['provenance'][$key]==='Projet'", "in_array($legalForm['provenance'][$key], ['Projet','Devis accepté'])");
edit('resources/views/livewire/admin/legal-document-fields.blade.php','<div class="field full" id="legal-checklist">',`@if(!empty($legalForm['snapshot']['commercial_scope']))
@php($scope = $legalForm['snapshot']['commercial_scope'])
<section class="field full admin-panel"><h3>Résumé du contrat de prestation</h3>
<p>Offre : {{ $scope['offer'] ?? 'Prestations personnalisées du devis' }}</p>
<p>Options : {{ implode(', ', $scope['options']) ?: 'Aucune' }}</p>
<p>Fonctionnalités personnalisées : {{ implode(', ', $scope['custom']) ?: 'Aucune' }}</p>
<p>Maintenance : {{ $legalForm['fields']['maintenance_mode'] }} · Données personnelles : {{ $legalForm['fields']['privacy_processing'] }}</p>
<p>Domaine : {{ $legalForm['fields']['domain_mode'] }} · Hébergement : {{ $legalForm['fields']['hosting_mode'] }}</p>
@if(!empty($legalForm['snapshot']['quote']['id']))<a class="text-link" href="{{ route('admin.billing.edit',['kind'=>'quotes','id'=>$legalForm['snapshot']['quote']['id']]) }}">Consulter / modifier le devis</a>@endif
</section>
@endif
<div class="field full" id="legal-checklist">`);
edit('resources/views/commercial/billing-pdf.blade.php',"{{ $line['description'] }}</div>","{{ $line['description'] }}</div>@if($kind==='quote')<div class=\"description lines\">{{ implode(\"\\n\", $line['contract_rules']['deliverables'] ?? []) }}</div>@if(!empty($line['contract_rules']['exclusions']))<div class=\"description lines\">Non compris : {{ implode(\" ; \", $line['contract_rules']['exclusions']) }}</div>@endif @endif");
