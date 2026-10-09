const fs=require('fs');function edit(p,a,b){let s=fs.readFileSync(p,'utf8');if(!s.includes(a))throw Error(p);fs.writeFileSync(p,s.replace(a,b));}
edit('app/Services/CommercialBilling.php',"$locked->snapshot = [...$locked->snapshot, 'offer_snapshot' => ContractCatalogRules::freeze($locked->items)];",`$locked->snapshot = [...$locked->snapshot, 'offer_snapshot' => ContractCatalogRules::freeze($locked->items)];
                $scope = app(ContractScopeBuilder::class)->build($locked);
                if ($scope['errors']) {
                    throw ValidationException::withMessages($scope['errors']);
                }`);
edit('app/Services/ProjectLegalDocuments.php',"        if ($document->type === 'terms' && $present($fields['additional']))",`        if ($document->type === 'terms' && ! empty($scope['clauses'])) {
            $sections['Conditions des prestations retenues'] = collect($scope['clauses'])->map(fn ($body, $title) => $title."\\n".$body)->implode("\\n\\n");
        }
        if ($document->type === 'terms' && $present($fields['additional']))`);
