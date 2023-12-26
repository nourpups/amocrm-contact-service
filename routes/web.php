<?php

use App\Http\Controllers\AmoCRM\AuthController;
use App\Http\Controllers\ContactController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Route;

Route::get('/auth', [AuthController::class, 'auth'])->name('amocrm.auth');

Route::redirect('/', 'contacts/create');
Route::get('/contacts/create', [ContactController::class, 'create'])->name('contacts.create');
Route::post('/contacts/create', [ContactController::class, 'store'])->name('contacts.store');
