@extends('pdf.layout')
@php use App\Support\Money; @endphp

@section('title', 'Business report')
@section('subtitle', $range->label())

@section('content')
    <table class="stats">
        <tr>
            <td><div class="muted">Bill volume</div><div class="value">{{ Money::format($summary['bill_amount']) }}</div><div class="muted">{{ $summary['entries'] }} entries</div></td>
            <td><div class="muted">Service charge</div><div class="value">{{ Money::format($summary['service_charge']) }}</div><div class="muted">Portal deduction {{ Money::format($summary['portal_deduction']) }}</div></td>
            <td><div class="muted">Collected from customers</div><div class="value success">{{ Money::format($summary['collected_amount']) }}</div><div class="muted">{{ $summary['payments_count'] }} payments</div></td>
            @if ($includeProfit)
                <td><div class="muted">Net profit (booked / collected)</div><div class="value">{{ Money::format($summary['net_profit']) }}</div><div class="muted">Collected {{ Money::format($summary['collected_profit']) }}</div></td>
            @else
                <td><div class="muted">Outstanding from this period</div><div class="value danger">{{ Money::format($summary['outstanding']) }}</div><div class="muted">of {{ Money::format($summary['total_due']) }} due</div></td>
            @endif
        </tr>
    </table>

    @foreach ($sections as $section)
        @if ($section['rows']->isNotEmpty())
            <h2>{{ $section['title'] }}</h2>
            <table class="grid">
                <thead>
                    <tr>
                        @foreach ($section['columns'] as $key => $label)
                            <th class="{{ in_array($key, ['name', 'mobile']) ? '' : 'num' }}">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($section['rows'] as $row)
                        <tr>
                            @foreach ($section['columns'] as $key => $label)
                                <td class="{{ in_array($key, ['name', 'mobile']) ? '' : 'num' }}">
                                    @if (in_array($key, ['name', 'mobile']))
                                        {{ $row[$key] ?? '' }}
                                    @elseif ($key === 'entries')
                                        {{ $row[$key] }}
                                    @else
                                        {{ Money::format($row[$key] ?? 0) }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="total">
                        @foreach ($section['columns'] as $key => $label)
                            <td class="{{ in_array($key, ['name', 'mobile']) ? '' : 'num' }}">
                                @if ($key === 'name')
                                    Total
                                @elseif ($key === 'mobile')
                                @elseif ($key === 'entries')
                                    {{ $section['rows']->sum('entries') }}
                                @else
                                    {{ Money::format(Money::fromPaise($section['rows']->sum(fn ($row) => Money::toPaise($row[$key] ?? 0)))) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        @endif
    @endforeach
@endsection
