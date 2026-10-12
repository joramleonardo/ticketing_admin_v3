<?php

it('redirects unauthenticated root visitor to login', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
