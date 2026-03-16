<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/', function (Illuminate\Http\Request $request) {
    if ($request->has('reset')) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    if (Auth::check()) {
        return redirect('/dashboard');
    }

    return view('pages.auth.login');
});

Route::middleware(['auth', 'verified'])->group(function () {

    Route::livewire('dashboard', 'pages::server.status')
        ->name('dashboard');
});


require __DIR__ . '/settings.php';
