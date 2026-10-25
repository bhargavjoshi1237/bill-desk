@extends('pdf.layout')
@php use App\Support\Money; $entry = $payment->entry; @endphp

@section('title', 'Payment receipt')
@section('subtitle', $payment->number().' · '.$payment->paid_on->format('d-m-Y'))

@section('content')
    <p style="font-size: 12px">
        Received with thanks from <strong>{{ $entry->customer->name }}</strong> ({{ $entry->customer->mobile }})
        the sum of <strong>{{ Money::format($payment->amount) }}</strong>
        by {{ $payment->mode->getLabel() }}@if ($payment->reference) (ref. {{ $payment->reference }})@endif,
        against entry {{ $entry->number() }} dated {{ $entry->entry_date->format('d-m-Y') }}.
    </p>

    <h2>Entry {{ $entry->number() }}</h2>
    <table class="kv">
        <tr><td class="label">Card</td><td class="num">{{ $entry->card?->label() ?: $entry->bank->name }}</td></tr>
        <tr><td class="label">Total payable</td><td class="num">{{ Money::format($entry->total_due) }}</td></tr>
        <tr><td class="label">Total received so far</td><td class="num success">{{ Money::format($entry->amount_received) }}</td></tr>
        <tr class="strong"><td>Balance due</td><td class="num {{ Money::toPaise($entry->outstanding) > 0 ? 'danger' : 'success' }}">{{ Money::format($entry->outstanding) }}</td></tr>
    </table>

    @if ($payment->notes)
        <h2>Notes</h2>
        <p>{{ $payment->notes }}</p>
    @endif

    <table style="width: 100%; margin-top: 48px"><tr>
        <td class="muted">Received by: {{ $payment->receiver?->name ?? '—' }}</td>
        <td class="muted" style="text-align: right">For {{ $business['business_name'] }}</td>
    </tr></table>
@endsection
