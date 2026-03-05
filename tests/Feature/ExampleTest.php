<?php

test('the public recipe homepage is accessible', function () {
    $response = $this->get('/');

    $response->assertSuccessful()->assertSee('Family Recipes');
});
