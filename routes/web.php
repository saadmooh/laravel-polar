<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('polar.path'),
    'as' => 'polar.',
], function () {
    Route::webhooks('webhook', 'polar');
});
