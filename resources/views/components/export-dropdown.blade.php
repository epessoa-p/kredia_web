{{--
    Reusable export dropdown button.
    Usage: @include('components.export-dropdown', ['baseUrl' => route('exports.loans'), 'params' => request()->all()])
    Generates: PDF Resumen, PDF Detallado, Excel Resumen, Excel Detallado links
--}}
@php
    $baseUrl = $baseUrl ?? '#';
    $params = $params ?? [];
    $queryString = http_build_query($params);
    $separator = str_contains($baseUrl, '?') ? '&' : '?';
@endphp

<div class="dropdown">
    <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-download me-1"></i> Exportar
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="min-width: 220px;">
        <li class="dropdown-header text-uppercase small fw-bold text-muted px-3">
            <i class="bi bi-file-earmark-pdf me-1"></i> PDF
        </li>
        <li>
            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ $baseUrl . $separator . $queryString . '&format=pdf&type=summary' }}" target="_blank">
                <span class="badge bg-danger-subtle text-danger rounded-pill" style="width:28px"><i class="bi bi-file-text"></i></span>
                Resumen
            </a>
        </li>
        <li>
            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ $baseUrl . $separator . $queryString . '&format=pdf&type=detailed' }}" target="_blank">
                <span class="badge bg-danger-subtle text-danger rounded-pill" style="width:28px"><i class="bi bi-file-richtext"></i></span>
                Detallado
            </a>
        </li>
        <li><hr class="dropdown-divider my-1"></li>
        <li class="dropdown-header text-uppercase small fw-bold text-muted px-3">
            <i class="bi bi-file-earmark-excel me-1"></i> Excel
        </li>
        <li>
            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ $baseUrl . $separator . $queryString . '&format=excel&type=summary' }}">
                <span class="badge bg-success-subtle text-success rounded-pill" style="width:28px"><i class="bi bi-file-text"></i></span>
                Resumen
            </a>
        </li>
        <li>
            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ $baseUrl . $separator . $queryString . '&format=excel&type=detailed' }}">
                <span class="badge bg-success-subtle text-success rounded-pill" style="width:28px"><i class="bi bi-file-richtext"></i></span>
                Detallado
            </a>
        </li>
    </ul>
</div>
