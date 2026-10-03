@php
    $extra = $extra ?? [];
    $today = now()->toDateString();
    $lastMonth = now()->subMonthNoOverflow();

    $presets = [
        'Hari ini' => [$today, $today],
        '7 hari' => [now()->subDays(6)->toDateString(), $today],
        'Bulan ini' => [now()->startOfMonth()->toDateString(), $today],
        'Bulan lalu' => [$lastMonth->copy()->startOfMonth()->toDateString(), $lastMonth->copy()->endOfMonth()->toDateString()],
        'Tahun ini' => [now()->startOfYear()->toDateString(), $today],
    ];

    $query = array_filter(array_merge($extra, ['from' => $period->fromDate(), 'to' => $period->toDate()]), fn ($v) => $v !== null && $v !== '');
@endphp

<form method="GET" class="card card-body border-0 shadow-sm mb-3">
    @foreach ($extra as $key => $value)
        @if ($value !== null && $value !== '')
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach

    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Dari tanggal</label>
            <input type="date" name="from" value="{{ $period->fromDate() }}" max="{{ $today }}" class="form-control" required>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1">Sampai tanggal</label>
            <input type="date" name="to" value="{{ $period->toDate() }}" class="form-control" required>
        </div>
        <div class="col-md-6 d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Terapkan</button>
            @isset($exportRoute)
                <a href="{{ route($exportRoute, $query) }}" class="btn btn-success"><i class="bi bi-download"></i> Ekspor CSV</a>
            @endisset
        </div>
    </div>

    <div class="d-flex flex-wrap gap-1 mt-3 align-items-center">
        <span class="small text-secondary me-1">Cepat:</span>
        @foreach ($presets as $label => [$from, $to])
            <a class="btn btn-sm btn-outline-secondary" href="{{ route($route, array_filter(array_merge($extra, ['from' => $from, 'to' => $to]), fn ($v) => $v !== null && $v !== '')) }}">{{ $label }}</a>
        @endforeach
        <span class="small text-secondary ms-auto">Periode: {{ $period->label() }} ({{ $period->days() }} hari)</span>
    </div>
</form>