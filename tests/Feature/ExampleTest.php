<?php

it('serves the welcome page', function () {
    $this->get('/')->assertOk()->assertSee('WIIS');
});
