<?php

namespace App\Http\Controllers;

use App\Application\Document\Query\GetPublicDocument\GetPublicDocumentHandler;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicDocumentController extends Controller
{
    public function __construct(
        private readonly GetPublicDocumentHandler $getPublicDocument,
    ) {}

    public function show(string $slug): View|Response
    {
        $payload = $this->getPublicDocument->execute($slug);

        if ($payload === null) {
            abort(404);
        }

        return view('public.document', [
            'title' => $payload['title'],
            'html' => $payload['html'],
        ]);
    }
}
