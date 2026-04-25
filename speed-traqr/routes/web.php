<?php

use App\Http\Controllers\PublicController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/track/{trackingNumber}', [PublicController::class, 'track'])->name('public.track');

Route::middleware('auth')->get('/scanner', function () {
    return view('scan', ['departments' => \App\Models\Department::all()]);
})->name('scanner');