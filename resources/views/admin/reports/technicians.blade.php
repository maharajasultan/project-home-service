@extends('admin.layouts.app')

@section('title', 'Kinerja Teknisi')
@section('page_title', 'Laporan Kinerja Teknisi')

@section('content')
@php use App\Support\Format; @endphp

<div class="card card-body border-0 shadow-sm mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.reports.technicians', ['month' => $prev]) }}" class="btn btn-outline-secondary" title="Bulan sebelumnya"><i class="bi bi-chevron-left"></i></a>
            <form method="GET" class="d-flex gap-2">
                <input type="month" name="month" value="{{ $month->format('Y-m') }}" max="{{ now()->format('Y-m') }}" class="form-control" required>
                <button class="btn btn-primary" type="submit">Tampilkan</button>
            </form>
            @if ($next)
                <a href="{{ route('admin.reports.technicians', ['month' => $next]) }}" class="btn btn-outline-secondary" title="Bulan berikutnya"><i class="bi bi-chevron-right"></i></a>
            @else
                <button class="btn btn-outline-secondary" disabled><i class="bi bi-chevron-right"></i></button>
            @endif
        </div>
        <a href="{{ route('admin.reports.technicians.export', ['month' => $month->format('Y-m')]) }}" class="btn btn-success"><i class="bi bi-download"></i> Ekspor CSV</a>
    </div>
    <div class="small text-secondary mt-2">Bulan: <strong>{{ $month->format('m/Y') }}</strong>. Pekerjaan dihitung dari tanggal selesai, rating dari ulasan yang dibuat pada bulan ini.</div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Pekerjaan Selesai</div>
        <div class="fs-4 fw-semibold text-success">{{ $totals['jobs'] }}</div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Terjadwal / Berjalan</div>
        <div class="fs-4 fw-semibold">{{ $totals['open'] }}</div>
        <div class="small text-secondary">Jadwal di bulan ini</div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Nilai Pekerjaan Selesai</div>
        <div class="fs-5 fw-semibold">{{ Format::rupiah($totals['value']) }}</div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Rating Rata-rata</div>
        <div class="fs-4 fw-semibold text-warning">{{ $totals['avg'] !== null ? '★ '.number_format($totals['avg'], 1) : '-' }}</div>
        <div class="small text-secondary">{{ $totals['reviews'] }} ulasan bulan ini</div>
    </div></div></div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">Pekerjaan Selesai per Bulan (6 bulan terakhir)</div>
    <div class="card-body">
        @if (count($chart['datasets']))
            <canvas id="perfChart" height="90"></canvas>
        @else
            <div class="text-center text-secondary py-4">Belum ada data teknisi.</div>
        @endif
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th>Teknisi</th><th class="text-center">Selesai</th><th class="text-center">Terjadwal</th>
                <th class="text-end">Nilai Pekerjaan</th><th>Rating Bulan Ini</th><th>Rating Keseluruhan</th>
                <th class="text-center">Belum Dibalas</th><th>Penilaian</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="fw-semibold">{{ $r['name'] }} @unless ($r['active'])<span class="badge text-bg-secondary">nonaktif</span>@endunless</td>
                    <td class="text-center fw-semibold">{{ $r['jobs'] }}</td>
                    <td class="text-center">{{ $r['open'] }}</td>
                    <td class="text-end">{{ Format::rupiah($r['value']) }}</td>
                    <td class="text-nowrap">
                        @if ($r['month_avg'] !== null)
                            <span class="text-warning">★ {{ number_format($r['month_avg'], 1) }}</span>
                            <span class="text-secondary small">({{ $r['month_reviews'] }})</span>
                        @else
                            <span class="text-secondary">-</span>
                        @endif
                    </td>
                    <td class="text-nowrap">
                        <span class="text-warning">★ {{ number_format($r['overall_avg'], 1) }}</span>
                        <span class="text-secondary small">({{ $r['overall_count'] }})</span>
                    </td>
                    <td class="text-center">
                        @if ($r['unreplied'] > 0)<span class="badge text-bg-warning">{{ $r['unreplied'] }}</span>@else<span class="text-secondary">0</span>@endif
                    </td>
                    <td><span class="badge {{ $r['badge'] }}">{{ $r['label'] }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-secondary py-5">Belum ada teknisi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white small text-secondary">
        Predikat berdasarkan rating bulan ini: Sangat Baik (4,5 ke atas), Baik (4,0 sampai 4,4), Cukup (3,0 sampai 3,9), Perlu Evaluasi (di bawah 3,0).
    </div>
</div>
@endsection

@push('scripts')
@if (count($chart['datasets']))
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    const perf = @json($chart);
    new Chart(document.getElementById('perfChart'), {
        type: 'bar',
        data: { labels: perf.labels, datasets: perf.datasets },
        options: { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
    });
</script>
@endif
@endpush