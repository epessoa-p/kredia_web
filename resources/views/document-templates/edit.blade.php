@extends('layouts.app')

@section('title', 'Editar Plantilla')

@section('page')
<div class="container-fluid">
    <x-page-header title="Editar Plantilla" icon="pencil-square" :subtitle="$documentTemplate->name" :back="route('document-templates.index')">
        <x-btn variant="secondary" icon="eye" :href="route('document-templates.show', $documentTemplate)">Vista previa</x-btn>
    </x-page-header>

    @include('document-templates.form')
</div>
@endsection
