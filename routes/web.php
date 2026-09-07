<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

if (app()->environment('local')) {
    Route::get('/debug-sentry', function () {
        throw new \RuntimeException('Sentry test exception from /debug-sentry.');
    });
}
