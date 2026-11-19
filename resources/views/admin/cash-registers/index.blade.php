@extends('layouts.app')

@section('title', 'Cajas')

@section('page')
<div class="container-fluid">
    <x-page-header title="Cajas" icon="cash-stack" subtitle="Gestión de cajas registradoras y sus asignaciones.">
            @if(auth()->user()->hasPermissionInCompany('reports.view', auth()->user()->getCurrentCompany()) && (auth()->user()->is_super_admin || auth()->user()->hasPermissionInCompany('cash-registers.report', auth()->user()->getCurrentCompany())))
                <x-btn variant="secondary" icon="bar-chart-line" :href="route('reports.cash-register-sessions')">Reporte aperturas/cierres</x-btn>
            @endif
            <x-btn icon="plus-lg" :href="route('cash-registers.create')">Nueva caja</x-btn>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Sucursal</th>
                            <th>Personal asignado</th>
                            <th>Estado</th>
                            <th>Sesión</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cashRegisters as $register)
                            <tr>
                                <td>
                                    <strong>{{ $register->name }}</strong>
                                    @if($register->description)
                                        <br><small class="text-muted">{{ Str::limit($register->description, 50) }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($register->branch)
                                        @php $bColor = $register->branch->color ?? '#6c757d'; @endphp
                                        <span style="display:inline-flex;align-items:center;gap:4px;font-size:var(--fs-sm);padding:2px 9px 2px 5px;border-radius:20px;background:{{ $bColor }}18;color:{{ $bColor }};border:1px solid {{ $bColor }}44;font-weight:500;white-space:nowrap;">
                                            <span style="width:7px;height:7px;border-radius:50%;background:{{ $bColor }};flex-shrink:0;"></span>
                                            {{ $register->branch->name }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $register->assignedPersonal?->full_name ?? '—' }}</td>
                                <td>
                                    @if($register->active)
                                        <span class="badge bg-success">Activa</span>
                                    @else
                                        <span class="badge bg-secondary">Inactiva</span>
                                    @endif
                                </td>
                                <td>
                                    @if($register->activeSession)
                                        <span class="badge bg-info"><i class="bi bi-unlock"></i> Abierta</span>
                                        <br><small class="text-muted">{{ $register->activeSession->opened_at->format('d/m/Y H:i') }}</small>
                                    @else
                                        <span class="badge bg-dark"><i class="bi bi-lock"></i> Cerrada</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <x-icon-btn icon="eye" label="Ver" :href="route('cash-registers.show', $register)" />
                                    <x-icon-btn icon="pencil" label="Editar" :href="route('cash-registers.edit', $register)" />
                                    <form action="{{ route('cash-registers.destroy', $register) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de eliminar esta caja?')">
                                        @csrf @method('DELETE')
                                        <x-icon-btn icon="trash" label="Eliminar" variant="danger" type="submit" />
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No hay cajas registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $cashRegisters->links() }}</div>
</div>
@endsection
