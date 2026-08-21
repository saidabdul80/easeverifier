<?php

use Illuminate\Support\Facades\Route;

test('email verification notification route is disabled', function () {
    expect(Route::has('verification.send'))->toBeFalse();
});
