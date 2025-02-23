<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('polar.path'),
    'as' => 'polar.',
], function () {
    Route::post('webhook', '\Spatie\WebhookClient\Http\Controllers\WebhookController')->name('webhook-client-polar');
});
