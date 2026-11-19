<?php

namespace App\Console\Commands;

use App\Models\ClientDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Mueve los documentos de clientes del disco público (storage/app/public) al privado.
 * Ejecutar UNA vez en cada servidor tras desplegar:  php artisan clients:move-documents-private
 * Es reejecutable: los que ya están en privado se saltan.
 */
class MoveClientDocumentsPrivate extends Command
{
    protected $signature = 'clients:move-documents-private {--dry-run : Solo mostrar lo que se movería}';

    protected $description = 'Mueve los documentos de clientes al almacenamiento privado';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk(ClientDocument::DISK);
        $moved = $skipped = $missing = 0;

        ClientDocument::query()->orderBy('id')->chunkById(200, function ($docs) use ($public, $private, &$moved, &$skipped, &$missing) {
            foreach ($docs as $doc) {
                if ($private->exists($doc->path)) {
                    $skipped++;
                    continue;
                }

                if (!$public->exists($doc->path)) {
                    $missing++;
                    $this->warn("No existe el archivo del documento #{$doc->id}: {$doc->path}");
                    continue;
                }

                if (!$this->option('dry-run')) {
                    $private->writeStream($doc->path, $public->readStream($doc->path));
                    $public->delete($doc->path);
                }
                $moved++;
            }
        });

        $this->info("Movidos: {$moved} · Ya privados: {$skipped} · Sin archivo: {$missing}");

        return self::SUCCESS;
    }
}
