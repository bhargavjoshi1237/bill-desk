@extends('pdf.layout')
@php use App\Support\Money; use App\Filament\Widgets\OutstandingCustomersTable; @endphp

@section('title', 'Outstanding report')
@section('subtitle', 'As of '.today()->format('d-m-Y'))

@section('content')
    <table class="stats">
        <tr>
            <td><div class="muted">Total outstanding</div><div class="value danger">{{ Money::format($position['outstanding']) }}</div><div class="muted">{{ $position['open_entries'] }} open entries · {{ $position['customers_owing'] }} customers</div></td>
            @foreach ($aging as $bucket)
                <td><div class="muted">{{ $bucket['label'] }}</div><div class="value">{{ Money::format($bucket['outstanding']) }}</div><div class="muted">{{ $bucket['entries'] }} entries</div></td>
            @endforeach
        </tr>
    </table>

    <h2>Customers</h2>
    <table class="grid">
        <thead><tr><th>Customer</th><th>Mobile</th><th class="num">Open entries</th><th class="num">Outstanding</th><th class="num">Overdue</th><th>Oldest unpaid</th><th class="num">Age (days)</th></tr></thead>
        <tbody>
            @forelse ($customers as $customer)
                <tr>
                    <td>{{ $customer->name }}</td>
                    <td>{{ $customer->mobile }}</td>
                    <td class="num">{{ $customer->open_entries_count }}</td>
                    <td class="num danger">{{ Money::format($customer->outstanding_total) }}</td>
                    <td class="num">{{ Money::format($customer->overdue_total ?? 0) }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($customer->oldest_open_entry_date)->format('d-m-Y') }}</td>
                    <td class="num">{{ OutstandingCustomersTable::age($customer) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No customer owes money right now.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($entries->isNotEmpty())
        <h2>Open entries</h2>
        <table class="grid">
            <thead><tr><th>Entry #</th><th>Date</th><th>Customer</th><th>Bank</th><th>Due date</th><th class="num">Total due</th><th class="num">Received</th><th class="num">Outstanding</th></tr></thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr>
                        <td>{{ $entry->number() }}</td>
                        <td>{{ $entry->entry_date->format('d-m-Y') }}</td>
                        <td>{{ $entry->customer?->name }} <span class="muted">{{ $entry->customer?->mobile }}</span></td>
                        <td>{{ $entry->bank?->name }}</td>
                        <td class="{{ $entry->isOverdue() ? 'danger' : '' }}">{{ $entry->due_date?->format('d-m-Y') ?? '—' }}</td>
                        <td class="num">{{ Money::format($entry->total_due) }}</td>
                        <td class="num">{{ Money::format($entry->amount_received) }}</td>
                        <td class="num danger">{{ Money::format($entry->outstanding) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
