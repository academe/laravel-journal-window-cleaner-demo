<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');

require __DIR__.'/demos/window-cleaner.php';
