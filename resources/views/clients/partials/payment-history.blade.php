{{-- Historial de pagos (calificación). Requiere $clientRating (ClientRating::forClient) y $ratingSettings. --}}
@php
    $ratingMeta = \App\Services\ClientRating::meta($clientRating->rating);
    $ratingHelp = [
        'fiel' => 'Puntual y con buen historial de préstamos. Buen candidato para otro tipo de préstamo.',
        'puntual' => 'Paga sus intereses a tiempo, sin multas ni incumplimientos.',
        'regular' => 'Tuvo intereses pagados con atraso o multas, pero hoy está al día.',
        'riesgo' => $ratingSettings['risk_include_mora'] ? 'Tiene hoy un préstamo en mora o incumplido.' : 'Tiene hoy un préstamo incumplido.',
        'nuevo' => 'Todavía no tiene historial de pagos.',
    ][$clientRating->rating] ?? '';
@endphp
<div class="card border-0 shadow-sm flex-grow-1" style="border-left: 4px solid {{ $ratingMeta['color'] }} !important;">
    <div class="card-body p-3 pb-2">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div>
                <div class="cs-stats-label mb-1"><i class="bi bi-award-fill me-1"></i> Historial de pagos</div>
                <div style="color: {{ $ratingMeta['color'] }}; font-size:var(--fs-sm);">
                    <i class="bi {{ $ratingMeta['icon'] }}"></i> <strong>{{ $ratingMeta['label'] }}</strong> — {{ $ratingHelp }}
                </div>
            </div>
            @if($clientRating->punctuality !== null)
                <div class="text-end flex-shrink-0">
                    <div class="fw-bold fs-5 lh-1" style="color: {{ $clientRating->punctuality >= 90 ? '#15803d' : ($clientRating->punctuality >= 70 ? '#a16207' : '#b91c1c') }}">{{ $clientRating->punctuality }}%</div>
                    <div class="text-muted fs-xs">puntualidad</div>
                </div>
            @endif
        </div>
        <div class="row g-2 text-center fs-sm">
            <div class="col-4"><div class="fw-bold">{{ $clientRating->loans_count }}</div><div class="text-muted fs-xs">Préstamos</div></div>
            <div class="col-4"><div class="fw-bold">{{ $clientRating->finished_count }}</div><div class="text-muted fs-xs">Finalizados</div></div>
            <div class="col-4"><div class="fw-bold">{{ $currencySymbol }} {{ number_format($clientRating->total_borrowed, 0, ',', '.') }}</div><div class="text-muted fs-xs">Prestado</div></div>
            <div class="col-4"><div class="fw-bold text-success">{{ $clientRating->on_time_interests }}</div><div class="text-muted fs-xs">Intereses a tiempo</div></div>
            <div class="col-4"><div class="fw-bold {{ $clientRating->late_interests ? 'text-danger' : '' }}">{{ $clientRating->late_interests }}</div><div class="text-muted fs-xs">Con atraso</div></div>
            <div class="col-4"><div class="fw-bold {{ $clientRating->risk_open_count ? 'text-danger' : '' }}">{{ $clientRating->risk_open_count }}</div><div class="text-muted fs-xs">En mora / incumplidos</div></div>
        </div>
        <div class="text-muted mt-2 fs-xs">
            "A tiempo" = pagado hasta {{ $ratingSettings['tolerance_days'] }} día(s) después del vencimiento.
            "Con atraso" incluye intereses vencidos que todavía no se pagaron.
        </div>
    </div>
</div>
