<div>
    <p class="eyebrow">Codenyr / Micro-entreprise</p>
    <h1>Comptabilité et URSSAF</h1>
    <p>Retrouve les encaissements du mois et estime le montant à mettre de côté. Tu peux préparer ton activité en faisant varier les options.</p>
    @include('livewire.admin.commercial-errors')

    <section class="admin-panel">
        <div class="form-grid">
            <div class="field"><label for="accounting-month">Mois des encaissements</label><input id="accounting-month" type="month" min="2000-01" max="2099-12" wire:model.live="month"></div>
            <div><p>Seuls les paiements enregistrés sur les factures sont comptabilisés, à leur date d’encaissement. Les factures impayées ne sont pas du chiffre d’affaires encaissé.</p><a class="button button-secondary" href="{{ route('admin.billing.invoices.index') }}">Factures et règlements</a></div>
        </div>
    </section>

    <section class="admin-panel">
        <h2>Mon régime pour ce mois</h2>
        <p>Choisis la catégorie qui figurera sur ton inscription URSSAF. Tous les encaissements ci-dessous sont traités dans cette seule catégorie ; ce calcul ne ventile pas les activités mixtes.</p>
        <form wire:submit="saveSettings">
            <div class="field"><label for="accounting-activity">Activité déclarée</label><select id="accounting-activity" wire:model.live="options.activity"><option value="">Choisir une catégorie</option>@foreach($profiles as $key => $profile)<option value="{{ $key }}">{{ $profile['label'] }}</option>@endforeach</select></div>
            <div class="form-grid" style="margin-top:20px">
                <div>
                    <label class="commercial-checkbox"><input type="checkbox" wire:model.live="options.acre"> ACRE applicable à ce mois</label>
                    @if($options['acre'] ?? false)
                        <div class="field"><label for="acre-scheme">Réduction accordée</label><select id="acre-scheme" wire:model.live="options.acre_scheme"><option value="25">25 % — créations à partir du 1er juillet 2026</option><option value="50">50 % — ancien dispositif, si encore applicable</option></select></div>
                    @endif
                    <p><small>À cocher uniquement pendant ta période d’exonération. L’ACRE doit être reconfirmée pour chaque nouveau mois ; la case ne fait aucune demande d’aide.</small></p>
                </div>
                <div><label class="commercial-checkbox"><input type="checkbox" wire:model.live="options.liberatory"> Versement libératoire de l’impôt sur le revenu</label><p><small>À cocher si tu as choisi cette option et y es éligible. Sinon, l’impôt sur le revenu n’est pas calculé ici.</small></p></div>
            </div>
            <label class="commercial-checkbox"><input type="checkbox" wire:model.live="options.custom"> Utiliser mes taux personnalisés</label>
            @if($options['custom'] ?? false)
                <p>Saisis les taux réellement applicables. Le taux social doit déjà inclure la réduction ACRE éventuelle : aucune réduction supplémentaire ne sera appliquée.</p>
                <div class="form-grid">
                    <div class="field"><label for="social-rate">Cotisations sociales effectives (%)</label><input id="social-rate" inputmode="decimal" wire:model.live.debounce.400ms="options.social_rate"></div>
                    <div class="field"><label for="training-rate">Formation professionnelle — CFP (%)</label><input id="training-rate" inputmode="decimal" wire:model.live.debounce.400ms="options.training_rate"></div>
                    @if($options['liberatory'] ?? false)<div class="field"><label for="tax-rate">Versement libératoire (%)</label><input id="tax-rate" inputmode="decimal" wire:model.live.debounce.400ms="options.tax_rate"></div>@endif
                </div>
            @else
                <p><small>Barèmes proposés : 2026, France métropolitaine. Pour une autre année ou un régime particulier, utilise tes taux personnalisés.</small></p>
            @endif
            <div class="field"><label for="other-rate">Autres contributions proportionnelles applicables (%)</label><input id="other-rate" inputmode="decimal" wire:model.live.debounce.400ms="options.other_rate"><small>0 par défaut. À compléter notamment si une taxe de chambre consulaire t’est applicable. La CFE et les charges fixes ne sont pas calculées ici.</small></div>
            <button class="button button-primary" wire:loading.attr="disabled">Enregistrer les options de ce mois</button>
        </form>
    </section>

    @if($guidance)<div class="form-alert" role="status"><ul>@foreach($guidance as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
    @if($report)
        @if($report['warnings'])<div class="form-alert" role="alert"><strong>Récapitulatif incomplet — anomalies à corriger</strong><ul>@foreach($report['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul></div>@endif
        <section class="admin-panel" aria-live="polite">
            <h2>Encaissements réels · {{ $month }}</h2>
            <div class="form-grid">
                <div><p>Encaissé TTC</p><h3>{{ \App\Services\CommercialCalculator::euros($report['ttc']) }}</h3></div>
                <div><p>Part de TVA</p><h3>{{ \App\Services\CommercialCalculator::euros($report['vat']) }}</h3></div>
                <div><p><strong>Chiffre d’affaires encaissé HT</strong></p><h3>{{ \App\Services\CommercialCalculator::euros($report['ht']) }}</h3></div>
                <div><p>Cumul HT de janvier à ce mois</p><h3>{{ \App\Services\CommercialCalculator::euros($report['year_ht']) }}</h3></div>
            </div>
            <p>Le HT est la base du calcul URSSAF, sans déduire les dépenses ni les commissions bancaires. Enregistre donc le paiement brut du client. Pour une facture réglée en plusieurs fois, la TVA est répartie au prorata du TTC, avec conservation des centimes au solde.</p>
            @if($estimate)
                @include('livewire.admin.accounting-estimate', ['calculation' => $estimate])
            @endif
            <p><small>Estimation au centime sur le CA exact ; les arrondis et le décompte du portail URSSAF font foi. Un mois à zéro reste à déclarer selon tes obligations. Ce module prépare les chiffres, sans transmettre de déclaration ni effectuer de paiement.</small></p>
        </section>

        <section class="admin-panel">
            <h2>Détail des encaissements</h2>
            <button type="button" class="button button-secondary" wire:click="export" wire:loading.attr="disabled" @disabled(count($report['warnings']) > 0)>Exporter les encaissements CSV</button>
            <div class="table-wrap">
                <table class="admin-table"><thead><tr><th scope="col">Date</th><th scope="col">Facture / client</th><th scope="col">Règlement</th><th scope="col">TTC</th><th scope="col">TVA</th><th scope="col">HT</th></tr></thead><tbody>
                    @forelse($report['rows'] as $row)
                        <tr wire:key="receipt-{{ $row['id'] }}"><td>{{ \Carbon\CarbonImmutable::parse($row['date'])->format('d/m/Y') }}</td><td><a href="{{ route('admin.billing.invoices.edit', ['id' => $row['invoice_id']]) }}">{{ $row['number'] }}</a><br>{{ $row['client'] }}</td><td>{{ $row['method'] }}<br>{{ $row['reference'] }}</td><td>{{ \App\Services\CommercialCalculator::euros($row['ttc']) }}</td><td>{{ \App\Services\CommercialCalculator::euros($row['vat']) }}</td><td>{{ \App\Services\CommercialCalculator::euros($row['ht']) }}</td></tr>
                    @empty<tr><td colspan="6">Aucun encaissement enregistré pour ce mois.</td></tr>@endforelse
                </tbody></table>
            </div>
            <p><small>Les paiements annulés sont exclus. L’archivage d’une facture ou d’un projet ne retire pas ses encaissements. Les corrections de paiement recalculent ce récapitulatif ; conserve ton export lors de chaque déclaration. Les recettes externes doivent d’abord être enregistrées dans Factures et règlements. Les remboursements et régularisations URSSAF ne sont pas automatisés.</small></p>
        </section>
    @endif

    <section class="admin-panel">
        <h2>Préparer mon activité</h2>
        <label class="commercial-checkbox"><input type="checkbox" wire:model.live="simulation"> Tester un chiffre d’affaires fictif</label>
        @if($simulation)
            <div class="field"><label for="simulated-revenue">Chiffre d’affaires mensuel HT à simuler (€)</label><input id="simulated-revenue" inputmode="decimal" wire:model.live.debounce.400ms="simulatedRevenue"></div>
            <p>Simulation uniquement : ce montant n’est ajouté ni aux factures ni aux encaissements. Les options choisies plus haut s’appliquent.</p>
            @if($simulated)@include('livewire.admin.accounting-estimate', ['calculation' => $simulated])@endif
        @endif
    </section>
    <details class="admin-panel"><summary>Sources officielles et périmètre</summary><p>Aide au suivi mensuel d’une micro-entreprise à activité unique, pas une comptabilité générale ni une déclaration de TVA. Vérifie ta catégorie, ton éligibilité aux options et tes taux après ton inscription.</p><ul>@foreach(config('urssaf.sources') as $label => $url)<li><a href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a></li>@endforeach</ul><p>Barèmes vérifiés le 9 octobre 2026.</p></details>
</div>
