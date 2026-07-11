<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\BlockController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\DocumentRevisionController;
use App\Http\Controllers\Api\V1\ImageController;
use App\Http\Controllers\Api\V1\MockStorageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:120,1')->group(function () {
    Route::get('mock-storage', [MockStorageController::class, 'show'])->name('mock.storage');
    Route::get('documents', [DocumentController::class, 'index']);
    Route::post('documents', [DocumentController::class, 'store'])->middleware('throttle:10,1');
    Route::get('documents/{document}', [DocumentController::class, 'show']);
    Route::get('documents/{document}/status', [DocumentController::class, 'status']);
    Route::post('documents/{document}/reprocess', [DocumentController::class, 'reprocess']);
    Route::get('documents/{document}/editor', [DocumentController::class, 'editor']);
    Route::put('documents/{document}', [DocumentController::class, 'update']);
    Route::get('documents/{document}/export.html', [DocumentController::class, 'exportHtml']);
    Route::get('documents/{document}/translated.docx', [DocumentController::class, 'downloadTranslated']);
    Route::delete('documents/{document}', [DocumentController::class, 'destroy']);

    Route::get('documents/{document}/revisions', [DocumentRevisionController::class, 'index']);
    Route::post('documents/{document}/revisions', [DocumentRevisionController::class, 'store']);
    Route::get('documents/{document}/revisions/{revision}', [DocumentRevisionController::class, 'show'])
        ->name('api.v1.documents.revisions.show');
    Route::post('documents/{document}/revisions/{revision}/restore', [DocumentRevisionController::class, 'restore']);

    Route::post('documents/{document}/blocks', [BlockController::class, 'store']);
    Route::put('documents/{document}/blocks/{block}', [BlockController::class, 'update']);
    Route::delete('documents/{document}/blocks/{block}', [BlockController::class, 'destroy']);
    Route::post('documents/{document}/blocks/{block}/duplicate', [BlockController::class, 'duplicate']);
    Route::patch('documents/{document}/blocks/reorder', [BlockController::class, 'reorder']);

    Route::post('documents/{document}/images', [ImageController::class, 'store']);
    Route::put('documents/{document}/images/{resource}', [ImageController::class, 'update']);
    Route::delete('documents/{document}/images/{resource}', [ImageController::class, 'destroy']);

    Route::post('documents/{document}/ai/rework', [AiController::class, 'rework'])->middleware('throttle:20,1');
});
