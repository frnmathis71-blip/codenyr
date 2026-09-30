<?php

test('landing example opens an independent clearly identified demonstration', function () {
    $this->get(route('services'))->assertOk()->assertSee(route('demo.landing'), false);
    $response = $this->get(route('demo.landing'))->assertOk()
        ->assertSee('Marque fictive')
        ->assertSee('Une certaine idée du café')
        ->assertDontSee('id="grind"', false)
        ->assertDontSee('coffee-dialog')
        ->assertDontSee('data-price')
        ->assertSee('noindex, nofollow')
        ->assertSee(route('services').'#landing', false);

    expect(substr_count($response->getContent(), '<h1'))->toBe(1);
});
