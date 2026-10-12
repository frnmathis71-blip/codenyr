<div style="margin-top:24px">
    <h3>Estimation des cotisations</h3>
    <dl class="commercial-totals">
        @foreach(['social' => 'Cotisations sociales', 'training' => 'Formation professionnelle (CFP)', 'tax' => 'Versement libératoire', 'other' => 'Autres contributions'] as $key => $label)
            <div><dt>{{ $label }} · {{ number_format($calculation['rates'][$key] / 100, 2, ',', ' ') }} %</dt><dd>{{ \App\Services\CommercialCalculator::euros($calculation['amounts'][$key]) }}</dd></div>
        @endforeach
        <div><dt><strong>Total estimé à provisionner</strong></dt><dd><strong>{{ \App\Services\CommercialCalculator::euros($calculation['total']) }}</strong></dd></div>
        <div><dt>Reste HT avant dépenses et impôt hors versement libératoire</dt><dd>{{ \App\Services\CommercialCalculator::euros($calculation['remaining']) }}</dd></div>
    </dl>
</div>
