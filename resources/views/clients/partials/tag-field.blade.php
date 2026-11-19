{{--
    Campo "Etiqueta" del cliente (clasificación manual, ej. CLIENTE FIEL, VIP).
    Las etiquetas se crean en Clientes → Categorías; aquí solo se elige una.
    Parámetros: $value (valor actual), $companyId (empresa del cliente).
--}}
@php
    $tagCompany = $companyId ? \App\Models\Company::find($companyId) : auth()->user()->getCurrentCompany();
    $tagOptions = \App\Models\ClientTag::forCompany($tagCompany?->id);
    $canTagField = auth()->user()->hasPermissionInCompany('clients.tag', $tagCompany);
    $canManageTags = auth()->user()->hasPermissionInCompany('clients.rating.settings', $tagCompany);
    $currentTag = old('tag', $value ?? '');
    $currentColor = $tagOptions->firstWhere('name', $value)?->color ?? \App\Models\ClientTag::DEFAULT_COLOR;
@endphp
<div class="col-md-6">
    <label class="form-label" for="clientTagSelect">
        <i class="bi bi-tag-fill text-primary"></i> Etiqueta
        <span class="text-muted fw-normal fs-sm">(opcional)</span>
    </label>

    @if($canTagField)
        <select name="tag" id="clientTagSelect" class="form-select form-select-sm @error('tag') is-invalid @enderror"
                @disabled($tagOptions->isEmpty() && !$value)>
            <option value="">{{ $tagOptions->isEmpty() && !$value ? 'Todavía no hay etiquetas creadas' : 'Sin etiqueta' }}</option>
            @foreach($tagOptions as $option)
                <option value="{{ $option->name }}" @selected($currentTag === $option->name)>{{ $option->name }}</option>
            @endforeach
            @if($value && !$tagOptions->contains('name', $value))
                <option value="{{ $value }}" selected>{{ $value }} (ya no está en la lista)</option>
            @endif
        </select>
        @error('tag')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text fs-xs">
            Clasificación manual del cliente. Se muestra destacada en la lista y se puede filtrar.
            @if($canManageTags)
                <a href="{{ route('clients.rating-settings.edit', auth()->user()->is_super_admin && $tagCompany ? ['company_id' => $tagCompany->id] : []) }}#etiquetas" target="_blank" class="text-decoration-none">
                    <i class="bi bi-sliders"></i> Administrar etiquetas
                </a>
            @else
                Las etiquetas se crean en Clientes → Categorías.
            @endif
        </div>
    @else
        <div class="form-control form-control-sm bg-light d-flex align-items-center gap-2" style="min-height: 31px;">
            @if($value)
                <span class="badge rounded-pill" style="background: {{ $currentColor }}; color: #fff;"><i class="bi bi-tag-fill"></i> {{ $value }}</span>
            @else
                <span class="text-muted">Sin etiqueta</span>
            @endif
        </div>
        <div class="form-text fs-xs"><i class="bi bi-lock"></i> Necesitas el permiso "Etiquetar clientes" para cambiarla.</div>
    @endif
</div>
