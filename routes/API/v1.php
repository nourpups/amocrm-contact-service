<?php

use App\Http\Controllers\API\ContactController;
use Illuminate\Support\Facades\Route;

Route::post('/contacts/create', [ContactController::class, 'store'])->name('contacts.store');
