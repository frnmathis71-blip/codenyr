<?php

return [
    // Standard metropolitan micro-social rates, checked against official sources on 2026-10-09.
    // All rates are hundredths of a percent; ACRE rates are explicit (not a floating-point discount).
    'year' => '2026',
    'profiles' => [
        'bnc' => ['label' => 'Activité libérale BNC — hors Cipav', 'social' => 2560, 'acre_50' => 1280, 'acre_25' => 1920, 'training' => 20, 'tax' => 220],
        'bic_services' => ['label' => 'Prestations de services BIC — commerciales', 'social' => 2120, 'acre_50' => 1060, 'acre_25' => 1590, 'training' => 20, 'tax' => 170],
        'bic_artisan' => ['label' => 'Prestations de services BIC — artisanales', 'social' => 2120, 'acre_50' => 1060, 'acre_25' => 1590, 'training' => 30, 'tax' => 170],
        'cipav' => ['label' => 'Profession libérale relevant de la Cipav', 'social' => 2320, 'training' => 20, 'tax' => 220],
        'sales' => ['label' => 'Vente de marchandises BIC', 'social' => 1230, 'acre_50' => 620, 'acre_25' => 930, 'training' => 10, 'tax' => 100],
    ],
    'sources' => [
        'Cotisations et catégories d’activité' => 'https://entreprendre.service-public.gouv.fr/vosdroits/F36232',
        'Barème ACRE 2026 — Urssaf' => 'https://www.urssaf.fr/files/live/sites/urssaffr/files/autres/Diaporama_Auto-entrepreneur.pdf',
        'Chiffre d’affaires encaissé et régime micro-social' => 'https://entreprendre.service-public.gouv.fr/vosdroits/F37353',
        'Contribution à la formation professionnelle' => 'https://mon-entreprise.urssaf.fr/simulateurs/auto-entrepreneur',
    ],
];
