<?php

return [
    'email' => env('CODENYR_EMAIL', 'contact@codenyr.fr'),
    'social' => array_filter(['LinkedIn' => env('CODENYR_LINKEDIN'), 'Instagram' => env('CODENYR_INSTAGRAM')]),
    'offers' => [
        'landing' => ['name' => 'Landing Page', 'price' => '490', 'intro' => 'Une page, un message. L’essentiel pour vous faire connaître.', 'features' => ['1 page, jusqu’à 6 sections', 'Design personnalisé et responsive', 'Formulaire de contact', 'SEO de base et favicon', 'HTTPS et mise en ligne', '2 séries de modifications']],
        'vitrine' => ['name' => 'Site Vitrine', 'price' => '790', 'intro' => 'Une présence en ligne à la hauteur de votre activité.', 'features' => ['Jusqu’à 5 pages personnalisées', 'Design responsive', 'Formulaire et SEO de base', 'Google Maps si nécessaire', 'Réseaux sociaux et favicon', 'HTTPS et mise en ligne', '2 séries de modifications']],
        'pro' => ['name' => 'Site Pro', 'price' => '1 190', 'intro' => 'Un site que vous pouvez faire vivre, en toute autonomie.', 'features' => ['Jusqu’à 10 pages', 'Espace d’administration', 'Base de données', 'Jusqu’à 2 modules CRUD', 'Design responsive et SEO', 'HTTPS et mise en ligne']],
        'custom' => ['name' => 'Sur mesure', 'price' => '1 690', 'intro' => 'Un outil pensé pour vos besoins, jusque dans les détails.', 'features' => ['Fonctionnalités métier sur devis', 'Réservation, commande ou panier', 'Paiement Stripe et comptes clients', 'Administration avancée', 'API et intégrations', 'Automatisations']],
    ],
    'features' => ['Formulaire', 'Administration', 'Réservation', 'Rendez-vous', 'Commande', 'Panier', 'Paiement', 'Comptes clients', 'Autre'],
    'budgets' => ['Moins de 500 €', '500–1 000 €', '1 000–2 000 €', '2 000–5 000 €', 'Plus de 5 000 €', 'Je ne sais pas'],
];
