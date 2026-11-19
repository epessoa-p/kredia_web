<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Documento de identidad / respaldo de un cliente.
 * Los archivos se guardan en el disco PRIVADO ('local' → storage/app/private) y solo se
 * sirven por un enlace firmado y temporal (no hay URL pública adivinable).
 */
class ClientDocument extends Model
{
    use Concerns\BelongsToCompany;

    public const DISK = 'local';
    public const URL_TTL_MINUTES = 30;

    protected $fillable = [
        'client_id', 'company_id', 'label',
        'path', 'original_name', 'mime_type', 'size_bytes',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Guarda un archivo subido en la carpeta privada del cliente y devuelve la ruta relativa. */
    public static function storeUpload(\Illuminate\Http\UploadedFile $file, int $clientId): string
    {
        return $file->store('client-docs/' . $clientId, self::DISK);
    }

    /** Enlace firmado y temporal para ver el archivo (web y app móvil). */
    public function getUrlAttribute(): string
    {
        return URL::temporarySignedRoute(
            'client-documents.file',
            now()->addMinutes(self::URL_TTL_MINUTES),
            ['document' => $this->id]
        );
    }

    /** Disco donde está físicamente el archivo (compatibilidad con archivos antiguos en 'public'). */
    public function storageDisk(): ?string
    {
        if (Storage::disk(self::DISK)->exists($this->path)) {
            return self::DISK;
        }

        return Storage::disk('public')->exists($this->path) ? 'public' : null;
    }

    public function deleteFile(): void
    {
        Storage::disk(self::DISK)->delete($this->path);
        Storage::disk('public')->delete($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
