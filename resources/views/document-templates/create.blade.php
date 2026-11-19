@extends('layouts.app')

@section('title', 'Nueva Plantilla')

@section('page')
<div class="container-fluid">
    <x-page-header title="Nueva Plantilla" icon="file-earmark-plus" subtitle="Crea una plantilla para contratos, boletas, recibos, etc." :back="route('document-templates.index')" />

    @include('document-templates.form')
</div>
@endsection
