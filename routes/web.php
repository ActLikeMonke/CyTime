<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('time', 'pages::time.index')->name('time.index');
    Route::livewire('time/calendar', 'pages::time.calendar')
        ->middleware('role:employee')
        ->name('time.calendar');
    Route::livewire('absences', 'pages::absences.index')->name('absences.index');
});

Route::middleware(['auth', 'role:supervisor'])->group(function () {
    Route::livewire('supervisor/dashboard', 'pages::supervisor.dashboard')
        ->can('viewAny', User::class)
        ->name('supervisor.dashboard');
    Route::livewire('supervisor/users', 'pages::supervisor.users')
        ->can('viewAny', User::class)
        ->name('supervisor.users.index');
    Route::livewire('supervisor/users/{user}', 'pages::supervisor.user-detail')
        ->can('view', 'user')
        ->name('supervisor.users.show');
    Route::livewire('supervisor/absences', 'pages::supervisor.absences')->name('supervisor.absences.index');
});

require __DIR__.'/settings.php';
