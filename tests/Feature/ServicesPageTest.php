<?php

test('the services page shows the maintenance beside the site offers', function () {
    $this->get(route('services'))->assertOk()
        ->assertSee('has-maintenance', false)->assertSee('price-divider', false)
        ->assertSeeInOrder(['Sur mesure', 'Maintenance', '29,99', 'Sauvegarde chaque jour, conservée 30 jours'])
        ->assertSee('sans engagement')
        ->assertDontSee('id="estimer"', false)->assertDontSee('Sérénité');
});

test('the home page no longer lists prices or the budget estimator', function () {
    $this->get(route('home'))->assertOk()
        ->assertDontSee('Des tarifs clairs')->assertDontSee('#estimer', false)
        ->assertSee('Découvrir les offres');
});
