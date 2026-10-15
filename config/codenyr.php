<?php

return [
    'landing_demo_url' => env('CODENYR_LANDING_DEMO_URL', 'http://demo-vitrine.test/demo/landing-page'),
    'vitrine_demo_url' => env('CODENYR_VITRINE_DEMO_URL', 'http://demo-vitrine.test/demo/site-vitrine'),
    'email' => env('CODENYR_EMAIL', 'contact@codenyr.fr'),
    // Test mode (on by default in local only): documents can be issued even when legal
    // information is missing, and every PDF is stamped "MODE TEST — sans valeur".
    'test_mode' => (bool) env('CODENYR_TEST_MODE', env('APP_ENV') === 'local'),
    // Mentions légales (LCEN art. 6). Empty values are shown as "en cours" on the public page.
    'legal' => [
        'owner' => env('CODENYR_LEGAL_OWNER', 'Mathis Fornari'),
        'status' => env('CODENYR_LEGAL_STATUS', 'Entrepreneur individuel (EI), micro-entreprise'),
        'address' => env('CODENYR_LEGAL_ADDRESS', '40 rue du Bourg, 71880 Châtenoy-le-Royal, France'),
        'phone' => env('CODENYR_LEGAL_PHONE', '07 67 71 14 94'),
        'siret' => env('CODENYR_LEGAL_SIRET'),
        'vat' => env('CODENYR_LEGAL_VAT', 'TVA non applicable, art. 293 B du CGI'),
        'mediator' => env('CODENYR_LEGAL_MEDIATOR'),
        // Printed on invoices so clients can pay by transfer straight away.
        'bank' => [
            'holder' => env('CODENYR_BANK_HOLDER', env('CODENYR_LEGAL_OWNER', 'Mathis Fornari')),
            'iban' => env('CODENYR_BANK_IBAN'),
            'bic' => env('CODENYR_BANK_BIC'),
        ],
        'host' => [
            'name' => 'OVH SAS',
            'address' => '2 rue Kellermann, 59100 Roubaix, France',
            'phone' => '1007 depuis la France (+33 9 72 10 10 07)',
            'registration' => 'RCS Lille Métropole 424 761 419',
            'url' => 'https://www.ovhcloud.com',
        ],
    ],
    'social' => array_filter(['LinkedIn' => env('CODENYR_LINKEDIN'), 'Instagram' => env('CODENYR_INSTAGRAM')]),
    'offers' => [
        'landing' => ['name' => 'Landing Page', 'description' => 'Une page unique pour présenter une offre, lancer une activité ou inviter vos visiteurs à vous contacter.', 'price' => '490', 'intro' => 'Une page, un message. L’essentiel pour vous faire connaître.', 'features' => ['1 page, jusqu’à 6 sections', 'Design personnalisé et responsive', 'Formulaire de contact', 'SEO de base et favicon', 'HTTPS et mise en ligne', '2 séries de modifications']],
        'vitrine' => ['name' => 'Site Vitrine', 'description' => 'Un site de présentation pour faire découvrir votre entreprise, vos services et vos réalisations, et permettre à vos visiteurs de vous contacter.', 'price' => '790', 'intro' => 'Une présence en ligne à la hauteur de votre activité.', 'features' => ['Jusqu’à 5 pages personnalisées', 'Design responsive', 'Formulaire et SEO de base', 'Google Maps si nécessaire', 'Réseaux sociaux et favicon', 'HTTPS et mise en ligne', '2 séries de modifications']],
        'pro' => ['name' => 'Site Pro', 'description' => 'Un site avec un espace privé pour gérer vous-même vos contenus, par exemple vos actualités ou vos réalisations.', 'price' => '1 190', 'intro' => 'Un site que vous pouvez faire vivre, en toute autonomie.', 'features' => ['Jusqu’à 10 pages', 'Espace d’administration', 'Base de données', 'Jusqu’à 2 modules CRUD', 'Design responsive et SEO', 'HTTPS et mise en ligne']],
        'custom' => ['name' => 'Sur mesure', 'description' => 'Un site ou une application conçu autour de votre activité. Le contenu et les fonctionnalités sont définis ensemble dans le devis selon vos besoins.', 'price' => '1 690', 'intro' => 'Un outil pensé pour vos besoins, jusque dans les détails.', 'features' => ['Fonctionnalités métier sur devis', 'Réservation, commande ou panier', 'Paiement Stripe et comptes clients', 'Administration avancée', 'API et intégrations', 'Automatisations']],
    ],
    'maintenance' => [
        'essential' => ['name' => 'Essentiel', 'price' => '19,90', 'intro' => 'Hébergement, SSL, sauvegardes et surveillance.', 'starting_from' => false],
        'pro' => ['name' => 'Pro', 'price' => '39,90', 'intro' => 'Les services Essentiel, la maintenance technique et une assistance.', 'starting_from' => false],
        'business' => ['name' => 'Business', 'price' => '69,90', 'intro' => 'Les services Pro et un suivi Laravel adapté à votre application.', 'starting_from' => true],
    ],
    'features' => ['Formulaire', 'Administration', 'Réservation', 'Rendez-vous', 'Commande', 'Panier', 'Paiement', 'Comptes clients', 'Autre'],
    'budgets' => ['Moins de 500 €', '500–1 000 €', '1 000–2 000 €', '2 000–5 000 €', 'Plus de 5 000 €', 'Je ne sais pas'],
];
