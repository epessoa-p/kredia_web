@extends('layouts.app')

@section('title', 'Clientes de la ruta')

@section('page')
<x-page-header :title="'Ruta: ' . $route->name" icon="signpost-split" :subtitle="'Cobrador: ' . ($route->collector?->full_name ?? 'sin asignar')" :back="route('collections.routes.index')" />

<form method="POST" action="{{ route('collections.routes.clients.update', $route) }}" id="routeClientsForm">
    @csrf @method('PUT')
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-list-ol text-mod"></i> Orden de visita <span class="fs-xs text-muted fw-normal">(usa las flechas para ordenar)</span></div>
                <ul class="list-group list-group-flush" id="assigned">
                    @foreach($assigned as $client)
                        <li class="list-group-item d-flex align-items-center gap-2" data-id="{{ $client->id }}">
                            <span class="badge text-bg-light border js-pos"></span>
                            <span class="flex-fill"><span class="fw-semibold">{{ $client->name }}</span><span class="d-block fs-xs text-muted">{{ $client->address }}</span></span>
                            <x-icon-btn icon="arrow-up" label="Subir" variant="ghost" class="js-up" />
                            <x-icon-btn icon="arrow-down" label="Bajar" variant="ghost" class="js-down" />
                            <x-icon-btn icon="x-lg" label="Quitar de la ruta" variant="ghost" class="js-remove" />
                        </li>
                    @endforeach
                </ul>
                <div class="card-body text-muted fs-sm js-empty {{ $assigned->isEmpty() ? '' : 'd-none' }}">Sin clientes. Agrégalos desde la lista de la derecha.</div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-people text-mod"></i> Clientes sin ruta</div>
                <div class="card-body pb-0"><input type="search" class="form-control form-control-sm" placeholder="Buscar cliente…" id="searchAvailable"></div>
                <ul class="list-group list-group-flush overflow-auto" style="max-height: 480px;" id="available">
                    @foreach($available as $client)
                        <li class="list-group-item d-flex align-items-center gap-2" data-id="{{ $client->id }}" data-search="{{ mb_strtolower($client->name . ' ' . $client->address) }}">
                            <span class="flex-fill"><span class="fw-semibold">{{ $client->name }}</span><span class="d-block fs-xs text-muted">{{ $client->address }}</span></span>
                            <x-icon-btn icon="plus-lg" label="Agregar a la ruta" variant="primary" class="js-add" />
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <x-btn variant="secondary" :href="route('collections.routes.index')">Cancelar</x-btn>
        <x-btn type="submit" icon="check-lg">Guardar orden</x-btn>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const assigned = document.getElementById('assigned'), available = document.getElementById('available');
    const icon = (n, l, cls, v) => `<button type="button" class="btn btn-icon-only btn-sm ${v} ${cls}" title="${l}" aria-label="${l}"><i class="bi bi-${n}"></i></button>`;

    function renumber() {
        [...assigned.children].forEach((li, i) => li.querySelector('.js-pos').textContent = i + 1);
        document.querySelector('.js-empty').classList.toggle('d-none', assigned.children.length > 0);
    }

    document.addEventListener('click', e => {
        const btn = e.target.closest('button');
        if (!btn) return;
        const li = btn.closest('li');
        if (btn.classList.contains('js-up') && li.previousElementSibling) li.parentNode.insertBefore(li, li.previousElementSibling);
        else if (btn.classList.contains('js-down') && li.nextElementSibling) li.parentNode.insertBefore(li.nextElementSibling, li);
        else if (btn.classList.contains('js-remove')) {
            li.querySelectorAll('button, .js-pos').forEach(el => el.remove());
            li.insertAdjacentHTML('beforeend', icon('plus-lg', 'Agregar a la ruta', 'js-add', 'btn-outline-primary'));
            available.prepend(li);
        } else if (btn.classList.contains('js-add')) {
            btn.remove();
            li.insertAdjacentHTML('afterbegin', '<span class="badge text-bg-light border js-pos"></span>');
            li.insertAdjacentHTML('beforeend', icon('arrow-up', 'Subir', 'js-up', 'btn-ghost') + icon('arrow-down', 'Bajar', 'js-down', 'btn-ghost') + icon('x-lg', 'Quitar de la ruta', 'js-remove', 'btn-ghost'));
            assigned.append(li);
        } else return;
        renumber();
    });

    document.getElementById('searchAvailable').addEventListener('input', e => {
        const q = e.target.value.toLowerCase();
        [...available.children].forEach(li => li.classList.toggle('d-none', !(li.dataset.search || '').includes(q)));
    });

    document.getElementById('routeClientsForm').addEventListener('submit', function () {
        [...assigned.children].forEach(li => this.insertAdjacentHTML('beforeend', `<input type="hidden" name="client_ids[]" value="${li.dataset.id}">`));
    });

    renumber();
})();
</script>
@endpush
