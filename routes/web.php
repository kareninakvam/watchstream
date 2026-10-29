<?php

use App\Http\Middleware\EnsureAdministrator;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin', function () {
    return response()->json(['application' => config('app.name')]);
})->middleware(EnsureAdministrator::class)->name('admin.index');
