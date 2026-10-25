@extends('pdf.layout')
@php use App\Support\Money; @endphp

@section('title', 'Account statement')
@section('subtitle', $range->label())

@section('content')
    <table class="two-col">
        <tr>
            <td>
                <table class="kv">
                    <tr><td class="label">Customer</td><td><strong>{{ $customer->name }}</strong></td></tr>
                    <tr><td class="label">Mobile</td><td>{{ $customer->mobile }}</td></tr>
                    @if ($customer->city)<tr><td class="label">City</td><td>{{ $customer->city }}</td></tr>@endif
                </table>
            </td>
            <td>
                <table class="kv">
                    <tr><td class="label">Opening balance</td><td class="num">{{ Money::format($statement['opening']) }}</td></tr>
                    <tr><td class="label">Bills paid for you (+)</td><td class="num">{{ Money::format($statement['debits']) }}</td></tr>
                    <tr><td class="label">Payments received (−)</td><td class="num success">{{ Money::format($statement['credits']) }}</td></tr>
                    <tr class="strong"><td>Closing balance</td><td class="num {{ Money::toPaise($statement['closing']) > 0 ? 'danger' : 'success' }}">{{ Money::format($statement['closing']) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <h2>Transactions</h2>
    <table class="grid">
        <thead><tr><th>Date</th><th>Ref.</th><th>Description</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead>
        <tbody>
            <tr>
                <td>{{ $range->from->format('d-m-Y') }}</td><td></td><td><em>Opening balance</em></td><td></td><td></td>
                <td class="num">{{ Money::format($statement['opening']) }}</td>
            </tr>
            @forelse ($statement['lines'] as $line)
                <tr>
                    <td>{{ $line['date']->format('d-m-Y') }}</td>
                    <td>{{ $line['number'] }}</td>
                    <td>{{ $line['description'] }}</td>
                    <td class="num">{{ $line['debit'] !== null ? Money::format($line['debit']) : '' }}</td>
                    <td class="num">{{ $line['credit'] !== null ? Money::format($line['credit']) : '' }}</td>
                    <td class="num">{{ Money::format($line['balance']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No transactions in this period.</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="3">Closing balance</td>
                <td class="num">{{ Money::format($statement['debits']) }}</td>
                <td class="num">{{ Money::format($statement['credits']) }}</td>
                <td class="num">{{ Money::format($statement['closing']) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($statement['open_entries']->isNotEmpty())
        <h2>Unpaid entries as of today</h2>
        <table class="grid">
            <thead><tr><th>Entry #</th><th>Date</th><th>Bank</th><th>Due date</th><th class="num">Total due</th><th class="num">Received</th><th class="num">Outstanding</th></tr></thead>
            <tbody>
                @foreach ($statement['open_entries'] as $entry)
                    <tr>
                        <td>{{ $entry->number() }}</td>
                        <td>{{ $entry->entry_date->format('d-m-Y') }}</td>
                        <td>{{ $entry->bank?->name }}</td>
                        <td class="{{ $entry->isOverdue() ? 'danger' : '' }}">{{ $entry->due_date?->format('d-m-Y') ?? '—' }}{{ $entry->isOverdue() ? ' (overdue)' : '' }}</td>
                        <td class="num">{{ Money::format($entry->total_due) }}</td>
                        <td class="num">{{ Money::format($entry->amount_received) }}</td>
                        <td class="num danger">{{ Money::format($entry->outstanding) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="6">Total outstanding</td>
                    <td class="num">{{ Money::format(Money::fromPaise($statement['open_entries']->sum(fn ($e) => Money::toPaise($e->outstanding)))) }}</td>
                </tr>
            </tbody>
        </table>
    @endif
@endsection
