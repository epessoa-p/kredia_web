<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Recibo {{ $loanCode }}</title>
<style>
    @page { margin: {{ $ticket ? '10px 12px' : '40px 50px' }}; }
    body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: {{ $ticket ? '9px' : '12px' }}; color: #111; margin: 0; }
    .wrap { {{ $ticket ? '' : 'width: 420px; margin: 0 auto; border: 1px solid #ccc; padding: 24px;' }} }
    .center { text-align: center; }
    .company { font-size: {{ $ticket ? '12px' : '17px' }}; font-weight: bold; }
    .muted { color: #555; }
    .title { font-size: {{ $ticket ? '11px' : '15px' }}; font-weight: bold; margin: 6px 0 2px; letter-spacing: 1px; }
    hr { border: 0; border-top: 1px dashed #333; margin: 6px 0; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 1px 0; vertical-align: top; }
    td.r { text-align: right; }
    .total td { font-size: {{ $ticket ? '12px' : '16px' }}; font-weight: bold; padding-top: 4px; }
    .sign { margin-top: {{ $ticket ? '28px' : '50px' }}; border-top: 1px solid #333; width: 70%; margin-left: 15%; padding-top: 2px; }
</style>
</head>
<body>
<div class="wrap">
    <div class="center">
        <div class="company">{{ $company?->name }}</div>
        @if($loan->branch)
            <div class="muted">{{ $loan->branch->name }}@if($loan->branch->phone) · Tel.: {{ $loan->branch->phone }}@endif</div>
            @if($loan->branch->address)<div class="muted">{{ $loan->branch->address }}</div>@endif
        @elseif($company?->phone)
            <div class="muted">Tel.: {{ $company->phone }}</div>
        @endif
        <div class="title">RECIBO DE PAGO</div>
        <div>N.º {{ str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT) }}</div>
    </div>
    <hr>
    <table>
        <tr><td>Fecha:</td><td class="r">{{ $payment->payment_date?->format('d/m/Y') }} {{ $payment->created_at?->format('H:i') }}</td></tr>
        <tr><td>Préstamo:</td><td class="r">{{ $loanCode }}</td></tr>
        <tr><td>Cliente:</td><td class="r">{{ $loan->client?->name ?? '-' }}</td></tr>
        @if($loan->client?->id_number)
            <tr><td>CI:</td><td class="r">{{ $loan->client->id_number }}</td></tr>
        @endif
        @if($loan->product)
            <tr><td>Prenda:</td><td class="r">{{ $loan->product->name }}</td></tr>
        @endif
    </table>
    <hr>
    <table>
        <tr><td>Concepto:</td><td class="r">{{ $typeLabel }}</td></tr>
        @if((float) $payment->capital > 0 && (float) $payment->interest > 0)
            <tr><td>Capital:</td><td class="r">{{ $money($payment->capital) }}</td></tr>
            <tr><td>Interés:</td><td class="r">{{ $money($payment->interest) }}</td></tr>
        @endif
        <tr><td>Método:</td><td class="r">{{ $methodLabel }}</td></tr>
        @if($payment->reference)
            <tr><td>Referencia:</td><td class="r">{{ $payment->reference }}</td></tr>
        @endif
        <tr class="total"><td>TOTAL:</td><td class="r">{{ $money($payment->amount) }}</td></tr>
    </table>
    <hr>
    <table>
        <tr><td>Saldo del préstamo:</td><td class="r">{{ $money($pending) }}</td></tr>
        <tr><td>Atendido por:</td><td class="r">{{ $cashier ?? '-' }}</td></tr>
        @if($payment->cashRegister)
            <tr><td>Caja:</td><td class="r">{{ $payment->cashRegister->name }}</td></tr>
        @endif
    </table>
    <div class="sign center">Firma</div>
    <p class="center muted">Gracias por su pago.</p>
</div>
</body>
</html>
