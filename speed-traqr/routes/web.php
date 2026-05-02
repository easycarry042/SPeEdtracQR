<?php

use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicController::class, 'index'])->name('home');

Route::get('/track/{trackingNumber}', [PublicController::class, 'track'])->name('public.track');

Route::get('/scanner', function () {
    return view('scan', ['departments' => \App\Models\Department::all()]);
})->name('scanner');