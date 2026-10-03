<?php

namespace App\Http\Controllers;

use App\Support\PrivateFiles;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    /** Streams a private upload inline after a per-record policy check. */
    public function show(string $kind, int $id): StreamedResponse
    {
        [$model, $attribute, $ability] = PrivateFiles::resolve($kind);

        $record = $model::query()->findOrFail($id);

        Gate::authorize($ability, $record);

        $path = $record->{$attribute};

        abort_if(blank($path) || ! Storage::disk('local')->exists($path), 404);

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf';

        $response = Storage::disk('local')->response($path, "{$kind}-{$id}.{$extension}");
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
