@extends('layouts.app')

@section('title', 'Nueva Caja')

@section('page')
@include('admin.cash-registers.form', [
    'action' => route('cash-registers.store'),
    'method' => 'POST',
    'cashRegister' => null,
])
@endsection
