@extends('layouts.app')

@section('title', 'Empresas - Sistema de Préstamos')

@push('styles')
<style>
    .companies-kpi {
        border: 1px solid #e4eaf3;
        border-radius: 12px;
        padding: 14px;
        background: #fff;
        height: 100%;
    }

    .companies-kpi .label {
        color: #64748b;
        font-size: var(--fs-sm);
        text-transform: uppercase;
    }

    .companies-kpi .value {
        font-size: var(--fs-xl);
        font-weight: 700;
    }

    .companies-table-card {
        border: 0;
        border-radius: 14px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
    }

    .company-logo-cell {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        border: 1px solid #d6deea;
        object-fit: cover;
        background: #f7fafc;
    }

    .cover-mini {
        width: 100px;
        height: 42px;
        border-radius: 8px;
        object-fit: cover;
        border: 1px solid #d6deea;
        background: #f7fafc;
    }

    .companies-table td,
    .companies-table th {
        vertical-align: middle;
    }
</style>
@endpush

@section('page')
<div class="container-fluid">
    <x-page-header title="Empresas" icon="building" subtitle="Administra identidad visual, datos fiscales y estado operativo de cada empresa.">
        <x-btn icon="plus-lg" :href="route('companies.create')">Nueva empresa</x-btn>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="companies-kpi">
                <div class="label">Total empresas</div>
                <div class="value text-primary">{{ number_format($companies->total()) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="companies-kpi">
                <div class="label">Empresas en esta pagina</div>
                <div class="value text-info">{{ number_format($companies->count()) }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="companies-kpi">
                <div class="label">Pagina actual</div>
                <div class="value text-success">{{ number_format($companies->currentPage()) }}</div>
            </div>
        </div>
    </div>

    <div class="card companies-table-card">
        <div class="card-body">
            <div class="table-responsive">
            <table class="table table-sm table-hover companies-table align-middle">
                <thead>
                    <tr>
                        <th>Logo</th>
                        <th>Portada</th>
                        <th>Nombre</th>
                        <th>RUC</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($companies as $company)
                        <tr>
                            <td>
                                @if($company->logo)
                                    <img src="{{ asset('storage/' . $company->logo) }}" alt="Logo {{ $company->name }}" class="company-logo-cell">
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($company->cover_image)
                                    <img src="{{ asset('storage/' . $company->cover_image) }}" alt="Portada {{ $company->name }}" class="cover-mini">
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $company->name }}</strong>
                                <div class="small text-muted">ID #{{ $company->id }}</div>
                            </td>
                            <td>{{ $company->ruc ?? '-' }}</td>
                            <td>{{ $company->email ?? '-' }}</td>
                            <td>{{ $company->phone ?? '-' }}</td>
                            <td>
                                <span class="badge {{ $company->active ? 'bg-success' : 'bg-danger' }}">
                                    {{ $company->active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td>
                                <x-icon-btn icon="eye" label="Ver" :href="route('companies.show', $company)" />
                                <x-icon-btn icon="pencil" label="Editar" :href="route('companies.edit', $company)" />
                                <form action="{{ route('companies.destroy', $company) }}" method="POST" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-btn icon="trash" label="Eliminar" variant="danger" type="submit" onclick="return confirm('¿Estás seguro?')" />
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            <div class="d-flex justify-content-center">
                {{ $companies->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
