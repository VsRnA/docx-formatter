<?php

use App\Http\Controllers\PublicDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('/p/{slug}', [PublicDocumentController::class, 'show'])->name('public.document');
