<?php

use App\Http\Controllers\BitcoinController;

Route::get('/bitcoin/wallet', [BitcoinController::class, 'wallet']);
Route::get('/bitcoin/balance/{address}', [BitcoinController::class, 'balance']);
Route::post('/bitcoin/tx', [BitcoinController::class, 'tx']);
Route::get('/bitcoin/tx/{tx}', [BitcoinController::class, 'txCheck']);
