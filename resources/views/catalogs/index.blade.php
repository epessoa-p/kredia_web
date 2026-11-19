@extends('layouts.app')

@section('title', 'Catálogos')

@push('styles')
<style>
    .catalog-card {
        border: 1px solid #e9ecef;
        border-radius: .75rem;
        transition: all .18s ease;
        overflow: hidden;
    }
    .catalog-card:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,.08);
        border-color: #c5cdd6;
    }
    .catalog-header-bar {
        height: 6px;
        width: 100%;
    }
    .catalog-meta {
        font-size: var(--fs-sm);
        color: #6c757d;
    }
    .badge-accessible {
        background: #d1fae5;
        color: #065f46;
    }
    .badge-expired {
        background: #fee2e2;
        color: #991b1b;
    }
    .badge-paused {
        background: #fef3c7;
        color: #92400e;
    }
    .public-link {
        font-size: var(--fs-sm);
        color: #6c757d;
        word-break: break-all;
    }
    .catalog-actions .btn {
        padding: .25rem .5rem;
        font-size: var(--fs-sm);
    }
</style>
@endpush

@section('page')
<div class="container-fluid py-4">
    {{-- Header --}}
    <x-page-header title="Catálogos" icon="megaphone" subtitle="Arma catálogos para compartir con tus clientes">
        @if(auth()->user()->hasPermissionInCompany('catalogs.create', auth()->user()->getCurrentCompany()))
            <x-btn icon="plus-lg" :href="route('catalogs.create')">Nuevo catálogo</x-btn>
        @endif
    </x-page-header>

    {{-- Filtros --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
                <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control form-control-sm" placeholder="Buscar por título..." style="max-width:260px">
                <select name="status" class="form-select form-select-sm" style="max-width:180px">
                    <option value="">Todos</option>
                    <option value="active" {{ ($status ?? '') === 'active' ? 'selected' : '' }}>Activos</option>
                    <option value="inactive" {{ ($status ?? '') === 'inactive' ? 'selected' : '' }}>Inactivos / Vencidos</option>
                </select>
                <x-icon-btn icon="search" label="Buscar" variant="primary" type="submit" />
                @if(($q ?? '') !== '' || ($status ?? '') !== '')
                    <x-btn variant="secondary" :href="route('catalogs.index')">Limpiar</x-btn>
                @endif
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($catalogs->isEmpty())
        <div class="text-center py-5 text-muted">
            <i class="bi bi-megaphone fs-xl" style="opacity:.3"></i>
            <p class="mt-2">No hay catálogos todavía</p>
        </div>
    @else
        @php
            $currentCompany = auth()->user()->getCurrentCompany();
            $canManageCatalogs = auth()->user()->hasPermissionInCompany('catalogs.create', $currentCompany);
            $canDeleteCatalogs = auth()->user()->hasPermissionInCompany('catalogs.delete', $currentCompany);
        @endphp
        <div class="row g-3">
            @foreach($catalogs as $catalog)
                <div class="col-md-6 col-lg-4">
                    <div class="catalog-card bg-white h-100 d-flex flex-column">
                        <div class="catalog-header-bar" style="background: {{ $catalog->cover_color ?? '#0d6efd' }}"></div>
                        <div class="p-3 flex-grow-1 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="fw-bold mb-0">{{ $catalog->title }}</h6>
                                @if($catalog->isAccessible())
                                    <span class="badge badge-accessible">Activo</span>
                                @elseif(!$catalog->active)
                                    <span class="badge badge-paused">Pausado</span>
                                @else
                                    <span class="badge badge-expired">Vencido</span>
                                @endif
                            </div>

                            @if($catalog->description)
                                <p class="text-muted mb-2 fs-base">{{ Str::limit($catalog->description, 80) }}</p>
                            @endif

                            <div class="catalog-meta mb-2">
                                <i class="bi bi-box-seam"></i> {{ $catalog->items_count }} producto{{ $catalog->items_count !== 1 ? 's' : '' }}
                                &middot;
                                <i class="bi bi-person"></i> {{ $catalog->createdBy->name ?? '—' }}
                            </div>
                            <div class="catalog-meta mb-2">
                                @if($catalog->branch)
                                    <span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:{{ $catalog->branch->color ?? '#6c757d' }}"></span>
                                    Sucursal {{ $catalog->branch->name }}
                                @else
                                    <i class="bi bi-diagram-3"></i> Todas las sucursales
                                @endif
                            </div>

                            @if($catalog->expires_at)
                                <div class="catalog-meta mb-2">
                                    <i class="bi bi-clock"></i>
                                    Vence: {{ $catalog->expires_at->format('d/m/Y H:i') }}
                                    @if($catalog->expires_at->isPast())
                                        <span class="text-danger fw-semibold">(vencido)</span>
                                    @endif
                                </div>
                            @endif

                            <div class="public-link mb-2">
                                <i class="bi bi-link-45deg"></i>
                                <a href="{{ $catalog->getPublicUrl() }}" target="_blank">{{ $catalog->getPublicUrl() }}</a>
                            </div>

                            <div class="mt-auto pt-2 d-flex gap-1 flex-wrap catalog-actions">
                                <x-btn variant="outline-primary" icon="eye" :href="route('catalogs.show', $catalog)">Ver</x-btn>
                                @if($canManageCatalogs)
                                    <x-icon-btn icon="pencil" label="Editar" variant="secondary" :href="route('catalogs.edit', $catalog)" />
                                    <form action="{{ route('catalogs.toggle-active', $catalog) }}" method="POST" class="d-inline">
                                        @csrf
                                        <x-icon-btn :icon="$catalog->active ? 'pause-fill' : 'play-fill'" :label="$catalog->active ? 'Pausar' : 'Activar'" :variant="$catalog->active ? 'secondary' : 'success'" type="submit" />
                                    </form>
                                @endif
                                <x-icon-btn icon="file-pdf" label="Exportar PDF" variant="secondary" :href="route('catalogs.export-pdf', $catalog)" />
                                @if($canDeleteCatalogs)
                                    <form action="{{ route('catalogs.destroy', $catalog) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este catálogo?')">
                                        @csrf @method('DELETE')
                                        <x-icon-btn icon="trash" label="Eliminar" variant="danger" type="submit" />
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $catalogs->links() }}
        </div>
    @endif
</div>
@endsection
