@extends('layouts.app')

@section('title', 'Editar Caja')

@section('page')
@include('admin.cash-registers.form', [
    'action' => route('cash-registers.update', $cashRegister),
    'method' => 'PUT',
    'cashRegister' => $cashRegister,
])
@endsection
