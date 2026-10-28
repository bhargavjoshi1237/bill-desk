@extends('pdf.layout')
@php use App\Support\Money; $status = $entry->displayStatus(); @endphp

@section('title', 'Receipt')
@section('subtitle', $entry->number().' · '.$entry->entry_date->format('d-m-Y'))

@section('content')
    <table class="two-col">
        <tr>
            <td>
                <h2>Customer</h2>
                <table class="kv">
                    <tr><td class="label">Name</td><td><strong>{{ $entry->customer->name }}</strong></td></tr>
                    <tr><td class="label">Mobile</td><td>{{ $entry->customer->mobile }}</td></tr>
                    <tr><td class="label">Card</td><td>{{ $entry->card?->label() ?: $entry->bank->name }}</td></tr>
                    @if ($entry->reference)<tr><td class="label">Portal transaction ID</td><td>{{ $entry->reference }}</td></tr>@endif
                </table>
            </td>
            <td>
                <h2>Status</h2>
                <table class="kv">
                    <tr><td class="label">Status</td><td><span class="badge badge-{{ $status->value }}">{{ $status->getLabel() }}</span></td></tr>
                    <tr><td class="label">Repayment due</td><td>{{ $entry->due_date?->format('d-m-Y') ?? '—' }}</td></tr>
                    @if ($entry->paid_at)<tr><td class="label">Fully paid on</td><td>{{ $entry->paid_at->format('d-m-Y') }}</td></tr>@endif
                    <tr><td class="label">Prepared by</td><td>{{ $entry->creator?->name ?? '—' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <h2>Amount</h2>
    <table class="kv">
        <tr><td class="label">Credit card bill paid</td><td class="num muted">{{ Money::format($entry->bill_amount) }}</td></tr>
        <tr><td class="label">Portal charge ({{ Money::percent($entry->portal_charge_percent) }})</td><td class="num">{{ Money::format($entry->portal_deduction) }}</td></tr>
        <tr><td class="label">Service charge ({{ Money::percent($entry->service_charge_percent) }})</td><td class="num">{{ Money::format($entry->service_charge) }}</td></tr>
        <tr class="strong"><td>Total payable</td><td class="num">{{ Money::format($entry->total_due) }}</td></tr>
        <tr><td class="label">Received</td><td class="num success">{{ Money::format($entry->amount_received) }}</td></tr>
        <tr class="strong"><td>Balance due</td><td class="num {{ Money::toPaise($entry->outstanding) > 0 ? 'danger' : 'success' }}">{{ Money::format($entry->outstanding) }}</td></tr>
    </table>

    @if ($entry->payments->isNotEmpty())
        <h2>Payments received</h2>
        <table class="grid">
            <thead><tr><th>Receipt #</th><th>Date</th><th>Mode</th><th>Reference</th><th class="num">Amount</th></tr></thead>
            <tbody>
                @foreach ($entry->payments->sortBy('paid_on') as $payment)
                    <tr>
                        <td>{{ $payment->number() }}</td>
                        <td>{{ $payment->paid_on->format('d-m-Y') }}</td>
                        <td>{{ $payment->mode->getLabel() }}</td>
                        <td>{{ $payment->reference ?? '—' }}</td>
                        <td class="num">{{ Money::format($payment->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($entry->notes)
        <h2>Notes</h2>
        <p>{{ $entry->notes }}</p>
    @endif

    <table style="width: 100%; margin-top: 48px"><tr>
        <td class="muted">Customer signature</td>
        <td class="muted" style="text-align: right">For {{ $business['business_name'] }}</td>
    </tr></table>
@endsection
