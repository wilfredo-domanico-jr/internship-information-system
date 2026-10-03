<?php

it('redirects the root to login', function () {
    $this->get('/')->assertRedirect('/login');
});
