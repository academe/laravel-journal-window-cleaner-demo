<?php

it('shows the demo repo landing page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('laravel-journal')
        ->assertSee('Window cleaner');
});

it('shows the window cleaner home page', function () {
    $this->get('/window-cleaner')
        ->assertOk()
        ->assertSee('Shiny & Sons');
});
