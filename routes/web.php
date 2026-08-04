<?php

use Illuminate\Support\Facades\Route;

/*
 * Every non-API path renders the SPA shell; vue-router takes it from there.
 */
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api|up|build|storage).*$')
    ->name('app');
