@extends('layouts.app')

@section('title', 'Rutas de cobranza')

@section('page')
<x-page-header title="Rutas de cobranza" icon="signpost-split" :count="$routes->count()" :back="route('collections.today')">
    <x-btn icon="plus-lg" data-bs-toggle="modal" data-bs-target="#routeModal" data-mode="create">Nueva ruta</x-btn>
</x-page-header>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-list align-middle mb-0">
            <thead><tr><th>Ruta</th><th>Cobrador</th><th>Sucursal</th><th>Clientes</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
                @forelse($routes as $route)
                    <tr>
                        <td><div class="fw-semibold">{{ $route->name }}</div><div class="fs-xs text-muted">{{ $route->description }}</div></td>
                        <td>{{ $route->collector?->full_name ?? '—' }}</td>
                        <td>{{ $route->branch?->name ?? '—' }}</td>
                        <td>{{ $route->clients_count }}</td>
                        <td><x-status-badge :tone="$route->active ? 'success' : 'muted'">{{ $route->active ? 'Activa' : 'Inactiva' }}</x-status-badge></td>
                        <td class="text-end text-nowrap">
                            <x-icon-btn icon="people" label="Clientes y orden de visita" :href="route('collections.routes.clients', $route)" />
                            <x-icon-btn icon="pencil" label="Editar" data-bs-toggle="modal" data-bs-target="#routeModal" data-mode="edit"
                                        data-action="{{ route('collections.routes.update', $route) }}"
                                        :data-route="json_encode($route->only(['name', 'description', 'collector_id', 'branch_id', 'active']))" />
                            <form action="{{ route('collections.routes.destroy', $route) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar la ruta {{ $route->name }}? Sus clientes quedarán sin ruta.')">
                                @csrf @method('DELETE')
                                <x-icon-btn icon="trash" label="Eliminar" variant="danger" type="submit" />
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Aún no hay rutas. Crea una y asígnale clientes y un cobrador.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="routeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('collections.routes.store') }}" class="modal-content" id="routeForm">
            @csrf
            <input type="hidden" name="_method" value="POST" id="routeMethod">
            <div class="modal-header"><h5 class="modal-title" id="routeTitle">Nueva ruta</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body row g-3">
                <div class="col-12"><label class="form-label" for="r_name">Nombre</label><input type="text" id="r_name" name="name" class="form-control" required></div>
                <div class="col-12"><label class="form-label" for="r_desc">Descripción / zona</label><input type="text" id="r_desc" name="description" class="form-control"></div>
                <div class="col-6"><label class="form-label" for="r_collector">Cobrador</label>
                    <select id="r_collector" name="collector_id" class="form-select"><option value="">— Sin asignar —</option>@foreach($collectors as $c)<option value="{{ $c->id }}">{{ $c->full_name }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label" for="r_branch">Sucursal</label>
                    <select id="r_branch" name="branch_id" class="form-select"><option value="">—</option>@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
                <div class="col-12"><div class="form-check"><input type="hidden" name="active" value="0"><input type="checkbox" class="form-check-input" id="r_active" name="active" value="1" checked><label class="form-check-label" for="r_active">Activa</label></div></div>
            </div>
            <div class="modal-footer">
                <x-btn variant="secondary" data-bs-dismiss="modal">Cancelar</x-btn>
                <x-btn type="submit" icon="check-lg">Guardar</x-btn>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('routeModal').addEventListener('show.bs.modal', e => {
    const btn = e.relatedTarget, form = document.getElementById('routeForm');
    const edit = btn.dataset.mode === 'edit';
    const data = edit ? JSON.parse(btn.dataset.route) : { name: '', description: '', collector_id: '', branch_id: '', active: true };
    form.action = edit ? btn.dataset.action : '{{ route('collections.routes.store') }}';
    document.getElementById('routeMethod').value = edit ? 'PUT' : 'POST';
    document.getElementById('routeTitle').textContent = edit ? 'Editar ruta' : 'Nueva ruta';
    form.name.value = data.name || '';
    form.description.value = data.description || '';
    form.collector_id.value = data.collector_id || '';
    form.branch_id.value = data.branch_id || '';
    document.getElementById('r_active').checked = !!data.active;
});
</script>
@endpush
