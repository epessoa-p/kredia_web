<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\ClientDocument;
use Illuminate\Support\Facades\Storage;

/**
 * Sirve el archivo de un documento de cliente.
 * La ruta exige firma válida y vigente (middleware 'signed'); la firma la genera
 * ClientDocument::url solo para quien ya pudo ver el cliente.
 */
class ClientDocumentFileController extends Controller
{
    public function __invoke(ClientDocument $document)
    {
        $disk = $document->storageDisk();
        abort_unless($disk, 404);

        return Storage::disk($disk)->response($document->path, $document->original_name ?: basename($document->path), [
            'Cache-Control' => 'private, max-age=600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
