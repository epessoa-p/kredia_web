@extends('layouts.app')

@section('title', 'Plantilla: ' . $documentTemplate->name)

@section('page')
<div class="container-fluid">

    {{-- Header --}}
    <x-page-header :title="$documentTemplate->name" icon="file-earmark-text" :back="route('document-templates.index')" class="mb-2" />
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-{{ $documentTemplate->type_badge }}">{{ $documentTemplate->type_label }}</span>
                @php
                    $sizeIcons = ['grande' => '📄', 'mediano' => '📄', 'pequeno' => '🧾'];
                    $sizeBg    = ['grande' => 'bg-info-subtle text-info', 'mediano' => 'bg-warning-subtle text-warning', 'pequeno' => 'bg-success-subtle text-success'];
                @endphp
                <span class="badge {{ $sizeBg[$documentTemplate->paper_size] ?? 'bg-secondary-subtle text-secondary' }} border">
                    {{ $sizeIcons[$documentTemplate->paper_size] ?? '📄' }} {{ $documentTemplate->paper_size_label }}
                </span>
                @if($documentTemplate->active)
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Activo</span>
                @else
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactivo</span>
                @endif
                @if(!$documentTemplate->company_id)
                    <span class="badge bg-dark">Global</span>
                @endif
            </div>
            @if($documentTemplate->description)
                <p class="text-muted mb-0">{{ $documentTemplate->description }}</p>
            @endif
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <x-btn variant="secondary" icon="file-earmark-word" :href="route('document-templates.download.word', array_merge(['documentTemplate' => $documentTemplate], $previewLoan ? ['loan_id' => $previewLoan->id] : []))">Word</x-btn>
            <x-btn variant="secondary" icon="file-earmark-pdf" :href="route('document-templates.export.pdf', array_merge(['documentTemplate' => $documentTemplate], $previewLoan ? ['loan_id' => $previewLoan->id] : []))" target="_blank">PDF</x-btn>
            <x-btn icon="pencil" :href="route('document-templates.edit', $documentTemplate)">Editar</x-btn>
            <form action="{{ route('document-templates.destroy', $documentTemplate) }}" method="POST"
                  onsubmit="return confirm('¿Eliminar la plantilla «{{ addslashes($documentTemplate->name) }}»?')">
                @csrf
                @method('DELETE')
                <x-btn variant="outline-danger" icon="trash" type="submit">Eliminar</x-btn>
            </form>
        </div>
    </div>

    <div class="row g-4">

        {{-- ── Left: Preview ─────────────────── --}}
        <div class="col-lg-8">

            {{-- Live preview with loan data --}}
            @if($previewContent && $previewLoan)
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-success bg-opacity-10 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-success">
                            <i class="bi bi-eye-fill"></i>
                            Vista previa con
                            <strong>Préstamo #{{ $previewLoan->id }}</strong>
                            – {{ $previewLoan->client?->name ?? 'Sin cliente' }}
                        </h6>
                        <x-btn variant="secondary" icon="x-lg" :href="route('document-templates.show', $documentTemplate)">Quitar préstamo</x-btn>
                    </div>
                    <div class="card-body">
                        <div class="border rounded p-4 bg-white rendered-content" style="min-height:200px;">
                            {!! $previewContent !!}
                        </div>
                    </div>
                </div>
            @endif

            {{-- Raw template with placeholders highlighted --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-file-ruled"></i> Contenido de la plantilla</h6>
                    <small class="text-muted">Las variables aparecen resaltadas</small>
                </div>
                <div class="card-body">
                    <div class="border rounded p-4 bg-light template-raw" style="min-height:200px; white-space: pre-wrap; font-family: inherit;">
                        @if($documentTemplate->content)
                            {!! preg_replace(
                                '/\{\{([a-z_]+)\}\}/',
                                '<mark class="bg-warning bg-opacity-50 rounded px-1"><code class="fs-sm">{{$1}}</code></mark>',
                                e($documentTemplate->content)
                            ) !!}
                        @else
                            <span class="text-muted">Sin contenido.</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Right: Apply to Loan + Variables --}}
        <div class="col-lg-4">

            {{-- Apply to loan --}}
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-lightning-charge"></i> Aplicar a un préstamo</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Selecciona un préstamo para previsualizar la plantilla con datos reales,
                        o aplicarla directamente al editor de contratos.
                    </p>

                    {{-- Quick preview form --}}
                    <form method="GET" action="{{ route('document-templates.show', $documentTemplate) }}"
                          class="mb-3">
                        <label class="form-label small fw-semibold">Vista previa con préstamo</label>
                        <div class="d-flex gap-2">
                            <select name="loan_id" class="form-select form-select-sm">
                                <option value="">— Seleccionar —</option>
                                @foreach($loans as $loan)
                                    <option value="{{ $loan->id }}"
                                        {{ $previewLoan?->id == $loan->id ? 'selected' : '' }}>
                                        PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }} – {{ $loan->client?->name ?? 'Sin cliente' }}
                                    </option>
                                @endforeach
                            </select>
                            <x-icon-btn icon="eye" label="Previsualizar" variant="secondary" type="submit" class="flex-shrink-0" />
                        </div>
                    </form>

                    <hr class="my-2">

                    {{-- Apply to contract form --}}
                    <form method="POST" action="{{ route('document-templates.apply-to-loan', $documentTemplate) }}"
                          onsubmit="return confirm('¿Aplicar esta plantilla al contrato del préstamo seleccionado? El contenido actual del contrato será reemplazado.')">
                        @csrf
                        <label class="form-label small fw-semibold">Aplicar al contrato</label>
                        <select name="loan_id" class="form-select form-select-sm mb-2" required>
                            <option value="">— Seleccionar préstamo —</option>
                            @foreach($loans as $loan)
                                <option value="{{ $loan->id }}">
                                    PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }} – {{ $loan->client?->name ?? 'Sin cliente' }}
                                </option>
                            @endforeach
                        </select>
                        <x-btn icon="lightning-charge-fill" :block="true" type="submit">Aplicar y abrir contrato</x-btn>
                    </form>
                </div>
            </div>

            {{-- Variables reference --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0"><i class="bi bi-braces"></i> Variables de la plantilla</h6>
                </div>
                <div class="card-body p-2" style="max-height: 380px; overflow-y: auto;">
                    @php
                        $groups = [
                            'Cliente'           => ['cliente_nombre', 'cliente_cedula', 'cliente_telefono', 'cliente_email', 'cliente_direccion'],
                            'Préstamo'          => ['prestamo_id', 'prestamo_monto', 'prestamo_tasa', 'prestamo_plazo', 'prestamo_cuota', 'prestamo_total', 'prestamo_saldo'],
                            'Liquidación / Operaciones' => ['capital_vigente', 'interes_pendiente', 'multa_monto', 'liquidacion_total', 'liquidacion_monto', 'liquidacion_fecha', 'liquidacion_metodo', 'liquidacion_referencia', 'liquidacion_notas', 'liquidacion_capital_vigente', 'liquidacion_interes_pendiente', 'liquidacion_multa'],
                            'Empresa/Sucursal'  => ['empresa_nombre', 'sucursal_nombre'],
                            'Fechas'            => ['fecha_actual', 'fecha_inicio', 'fecha_fin'],
                            'Firmas'            => ['firma_prestamista', 'firma_cliente'],
                        ];
                        $placeholders = \App\Models\DocumentTemplate::PLACEHOLDERS;
                    @endphp

                    @foreach($groups as $groupName => $keys)
                        <div class="mb-2">
                            <div class="text-muted fw-semibold mb-1 px-1 fs-xs"
                                 style="letter-spacing: .05em; text-transform: uppercase;">
                                {{ $groupName }}
                            </div>
                            @foreach($keys as $key)
                                @php($token = sprintf('{{%s}}', $key))
                                <div class="d-flex align-items-start gap-2 px-1 py-1 rounded hover-bg-light copy-row"
                                     style="cursor: pointer;"
                                     onclick="copyVar(this, '{{ $token }}')"
                                     title="Clic para copiar">
                                    <code class="text-primary flex-shrink-0 fs-xs">{{ $token }}</code>
                                    <span class="text-muted fs-xs">{{ $placeholders[$key] ?? '' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function copyVar(el, text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                el.classList.add('bg-success-subtle');
                setTimeout(() => el.classList.remove('bg-success-subtle'), 800);
            });
        }
    }
</script>
@endpush
@endsection
