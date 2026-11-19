@extends('layouts.app')

@section('title', 'Nuevo Catálogo')

@section('page')
<div class="container-fluid py-3">
    @include('catalogs.form')
</div>
@endsection
