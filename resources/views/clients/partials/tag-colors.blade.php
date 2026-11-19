{{--
    Selector de color de una etiqueta (muestras redondas).
    Parámetros: $prefix (único por formulario), $selected (color actual o null).
--}}
@php $selected = $selected ?: \App\Models\ClientTag::DEFAULT_COLOR; @endphp
<div class="rs-swatches">
    @foreach(\App\Models\ClientTag::COLORS as $hex => $name)
        <input type="radio" class="btn-check" name="color" id="{{ $prefix }}Color{{ $loop->index }}" value="{{ $hex }}" @checked($selected === $hex)>
        <label for="{{ $prefix }}Color{{ $loop->index }}" class="rs-swatch" style="--sw: {{ $hex }};" title="{{ $name }}"></label>
    @endforeach
</div>
