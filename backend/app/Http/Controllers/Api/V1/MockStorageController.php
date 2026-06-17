<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MockStorageController extends Controller
{
    public function show(Request $request): BinaryFileResponse|Response
    {
        if (! config('services.integrations.mock_storage')) {
            abort(404);
        }

        $key = $request->query('key');
        if (! is_string($key) || $key === '') {
            abort(400, 'key required');
        }

        $root = storage_path('app/'.config('services.mock.storage_path', 'mock-cloud'));
        $key = ltrim(str_replace(['..', '\\'], ['', '/'], $key), '/');
        $path = $root.'/'.$key;

        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path);
    }
}
