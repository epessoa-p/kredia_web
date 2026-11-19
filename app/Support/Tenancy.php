<?php

namespace App\Support;

/**
 * Empresa activa (tenant) durante la petición actual.
 * - Web: la fija SetTenant desde la sesión.
 * - API: la fija AuthenticateApiToken desde la empresa del token.
 * - Superadmin, consola, rutas públicas: null ⇒ SIN filtro.
 *
 * Registrada como scoped (una instancia por petición) en AppServiceProvider.
 */
class Tenancy
{
    private ?int $companyId = null;

    public function set(?int $companyId): void
    {
        $this->companyId = $companyId ?: null;
    }

    public function id(): ?int
    {
        return $this->companyId;
    }

    public function active(): bool
    {
        return $this->companyId !== null;
    }

    /** Ejecuta $callback sin filtro de empresa (uso puntual y explícito). */
    public function withoutTenant(callable $callback): mixed
    {
        $previous = $this->companyId;
        $this->companyId = null;

        try {
            return $callback();
        } finally {
            $this->companyId = $previous;
        }
    }

    /** Ejecuta $callback como si la empresa activa fuera $companyId. */
    public function runAs(?int $companyId, callable $callback): mixed
    {
        $previous = $this->companyId;
        $this->companyId = $companyId;

        try {
            return $callback();
        } finally {
            $this->companyId = $previous;
        }
    }
}
