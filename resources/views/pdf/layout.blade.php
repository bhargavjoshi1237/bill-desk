<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { margin: 28px 32px 48px; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1f2328; line-height: 1.4; }
        h1 { font-size: 17px; margin: 0; }
        h2 { font-size: 12px; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #d0d7de; }
        .muted { color: #59636e; }
        .header { width: 100%; border-bottom: 2px solid #4f46e5; padding-bottom: 8px; margin-bottom: 12px; }
        .header td { vertical-align: top; }
        .business-name { font-size: 15px; font-weight: bold; color: #312e81; }
        .doc-title { font-size: 14px; font-weight: bold; text-align: right; text-transform: uppercase; letter-spacing: 1px; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { background: #eef2ff; color: #312e81; font-weight: bold; text-align: left; padding: 5px 6px; border-bottom: 1px solid #c7d2fe; }
        table.grid td { padding: 4px 6px; border-bottom: 1px solid #eaeef2; vertical-align: top; }
        table.grid tr.total td { font-weight: bold; border-top: 1.5px solid #1f2328; border-bottom: none; background: #f6f8fa; }
        .num { text-align: right; white-space: nowrap; }
        table.kv { width: 100%; border-collapse: collapse; }
        table.kv td { padding: 4px 6px; border-bottom: 1px solid #eaeef2; }
        table.kv td.label { color: #59636e; width: 45%; }
        table.kv tr.strong td { font-weight: bold; font-size: 12px; border-top: 1.5px solid #1f2328; }
        .two-col { width: 100%; }
        .two-col > tbody > tr > td { width: 50%; vertical-align: top; padding-right: 12px; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 9px; font-weight: bold; }
        .badge-paid { background: #dafbe1; color: #116329; }
        .badge-partial { background: #ddf4ff; color: #0550ae; }
        .badge-unpaid { background: #fff8c5; color: #7d4e00; }
        .badge-overdue { background: #ffebe9; color: #a40e26; }
        .stats { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 6px; }
        .stats td { border: 1px solid #d0d7de; border-radius: 4px; padding: 6px 8px; width: 25%; }
        .stats .value { font-size: 13px; font-weight: bold; }
        .danger { color: #a40e26; }
        .success { color: #116329; }
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; font-size: 8px; color: #59636e; border-top: 1px solid #d0d7de; padding-top: 4px; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    <div class="footer">
        <table style="width: 100%"><tr>
            <td>{{ $business['receipt_footer'] ?? '' }}</td>
            <td style="text-align: right">Generated {{ $generatedAt->format('d-m-Y h:i A') }} · Page <span class="page-number"></span></td>
        </tr></table>
    </div>

    <table class="header">
        <tr>
            <td>
                <div class="business-name">{{ $business['business_name'] }}</div>
                @if (filled($business['business_address']))<div class="muted">{!! nl2br(e($business['business_address'])) !!}</div>@endif
                @if (filled($business['business_phone']))<div class="muted">Phone: {{ $business['business_phone'] }}</div>@endif
            </td>
            <td>
                <div class="doc-title">@yield('title')</div>
                <div class="muted" style="text-align: right">@yield('subtitle')</div>
            </td>
        </tr>
    </table>

    @yield('content')
</body>
</html>
