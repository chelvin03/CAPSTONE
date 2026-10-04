<?php

it('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertOk()->assertSee('Welcome to')->assertSee('MCST Gymnasium')
        ->assertSee(route('reservation.create'))->assertSee(route('reservation.track'));
});
