<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IfthenpayController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/api/payments/ifthenpay/checkout', [IfthenpayController::class, 'checkout']);
Route::post('/api/payments/ifthenpay/callback', [IfthenpayController::class, 'callback']);
