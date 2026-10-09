const fs=require('fs');
function edit(p,a,b){let s=fs.readFileSync(p,'utf8');if(!s.includes(a))throw Error(p+' missing '+a);fs.writeFileSync(p,s.replace(a,b));}
edit('app/Models/Service.php',"'active' => 'boolean',","'contract_rules' => 'array', 'active' => 'boolean',");
edit('app/Services/CommercialCalculator.php',"'items.*.optional' =>","'items.*.contract_included' => 'sometimes|boolean', 'items.*.contract_rules' => 'sometimes|array', 'items.*.contract_key' => 'sometimes|nullable|string|max:200',\n            'items.*.optional' =>");
edit('app/Services/CommercialCalculator.php','$price = self::decimal',"$rules = ContractCatalogRules::validate($item['contract_rules'] ?? []);\n            $price = self::decimal");
edit('app/Services/CommercialCalculator.php',"$normalized[] = ['category'","$normalized[] = ['contract_rules' => $rules, 'contract_key' => $item['contract_key'] ?? null, 'contract_included' => (bool) ($item['contract_included'] ?? true), 'category'");
edit('app/Livewire/Admin/Billing.php',"=> ['category' => $item","=> ['contract_rules' => $item['contract_rules'] ?? [], 'contract_key' => $item['contract_key'] ?? null, 'contract_included' => $item['contract_included'] ?? true, 'category' => $item");
edit('app/Livewire/Admin/Billing.php',"$this->items[] = ['category'","$this->items[] = ['contract_rules' => [], 'contract_included' => true, 'category'");
edit('app/Livewire/Admin/Billing.php',"'category' => $service->category,","'contract_rules' => \\App\\Services\\ContractCatalogRules::forService($service), 'contract_key' => $service->price_key ?: ($service->catalog_key ?: 'service_'.$service->id), 'category' => $service->category,");
edit('app/Services/CommercialBilling.php',"            $locked->update(['number'","            if ($locked instanceof Quote) {\n                $locked->snapshot = [...$locked->snapshot, 'offer_snapshot' => ContractCatalogRules::freeze($locked->items)];\n            }\n            $locked->update(['number'");
edit('app/Services/ProjectLegalDocuments.php','        $activeMaintenance =',`        $scope = $quote ? app(ContractScopeBuilder::class)->build($quote) : null;
        if ($scope && $scope['frozen']) {
            foreach ($scope['fields'] as $key => $field) {
                $fields[$key] = $field;
                $provenance[$key] = 'Devis accepté';
            }
            foreach ($scope['errors'] as $key => $message) {
                $check($key, false, $message);
            }
        }
        $activeMaintenance =`);
edit('app/Services/ProjectLegalDocuments.php',"$check('maintenance_source', $maintenance->isNotEmpty(),","$check('maintenance_source', $maintenance->isNotEmpty() || ! empty($scope['clauses']['Maintenance']),");
edit('app/Services/ProjectLegalDocuments.php',"            $sections['Clauses applicables'] =",`            if ($scope && $scope['frozen']) {
                $sections['Périmètre de la prestation'] = $scope['scope'];
                foreach ($scope['clauses'] as $title => $body) {
                    $sections['Option — '.$title] = $body;
                }
                if (! empty($scope['clauses']['Maintenance'])) {
                    $sections['Maintenance'] = $scope['clauses']['Maintenance'];
                }
            }
            $sections['Clauses applicables'] =`);
edit('app/Services/ProjectLegalDocuments.php',"$snapshot = ['seller'","$snapshot = ['commercial_scope' => $scope, 'seller'");
