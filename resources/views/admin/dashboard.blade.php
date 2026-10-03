@extends('admin.layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@section('content')
@php
    use App\Support\Format;
    $cards = [
        ['Total Pesanan', $stats['orders_total'], 'bi-bag-check', 'primary', null],
        ['Pendapatan Bulan Ini', Format::rupiah($stats['revenue_month']), 'bi-cash-stack', 'success', 'Hari ini: '.Format::rupiah($stats['revenue_today'])],
        ['Total Pendapatan', Format::rupiah($stats['revenue_total']), 'bi-wallet2', 'success', null],
        ['Menunggu Pembayaran', $stats['orders_pending'], 'bi-hourglass-split', 'warning', null],
        ['Sedang Diproses', $stats['orders_processing'], 'bi-tools', 'info', null],
        ['Selesai', $stats['orders_completed'], 'bi-check2-circle', 'success', 'Gagal: '.$stats['orders_failed']],
        ['Pelanggan', $stats['customers'], 'bi-people', 'secondary', null],
        ['Teknisi Aktif', $stats['technicians'], 'bi-person-gear', 'secondary', null],
    ];
@endphp

<div class="row g-3 mb-4">
    @foreach ($cards as [$label, $value, $icon, $color, $hint])
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-{{ $color }}-subtle text-{{ $color }}-emphasis"><i class="bi {{ $icon }}"></i></div>
                    <div class="min-w-0">
                        <div class="text-secondary small">{{ $label }}</div>
                        <div class="fs-5 fw-semibold text-break">{{ $value }}</div>
                        @if ($hint)<div class="text-secondary small">{{ $hint }}</div>@endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@if ($stats['pending_claims'] > 0 || $stats['low_stock'] > 0)
    <div class="row g-3 mb-4">
        @if ($stats['pending_claims'] > 0)
            <div class="col-md-6">
                <a href="{{ route('admin.warranty.index') }}" class="alert alert-warning d-flex justify-content-between mb-0 text-decoration-none">
                    <span><i class="bi bi-shield-exclamation"></i> <strong>{{ $stats['pending_claims'] }}</strong> klaim garansi menunggu ditinjau</span>
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        @endif
        @if ($stats['low_stock'] > 0)
            <div class="col-md-6">
                <div class="alert alert-danger d-flex justify-content-between mb-0">
                    <span><i class="bi bi-box-seam"></i> <strong>{{ $stats['low_stock'] }}</strong> varian sparepart stoknya menipis (3 atau kurang)</span>
                </div>
            </div>
        @endif
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Pendapatan 14 Hari Terakhir</div>
            <div class="card-body"><canvas id="revenueChart" height="110"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Komposisi Status Pesanan</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <div style="max-width: 280px; width: 100%;"><canvas id="statusChart"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Pesanan Terbaru</span>
                <a href="{{ route('admin.transactions.index') }}" class="small">Lihat semua</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>Kode</th><th>Pelanggan</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($recentOrders as $order)
                        <tr>
                            <td><a href="{{ route('admin.transactions.show', $order->id) }}">{{ $order->order_code }}</a></td>
                            <td>{{ $order->user?->name ?? '-' }}</td>
                            <td>{{ Format::rupiah($order->total) }}</td>
                            <td><span class="badge {{ Format::statusBadge($order->status) }}">{{ Format::statusLabel($order->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-secondary py-4">Belum ada pesanan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Klaim Garansi Menunggu</div>
            <ul class="list-group list-group-flush">
                @forelse ($pendingClaims as $claim)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong>{{ $claim->user?->name }}</strong>
                            <a href="{{ route('admin.transactions.show', $claim->order_id) }}" class="small">{{ $claim->order?->order_code }}</a>
                        </div>
                        <div class="small text-secondary text-truncate">{{ $claim->reason }}</div>
                    </li>
                @empty
                    <li class="list-group-item text-center text-secondary py-4">Tidak ada klaim yang menunggu.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    const revenue = @json($revenueChart);
    const status = @json($statusChart);
    const rupiah = (v) => 'Rp' + new Intl.NumberFormat('id-ID').format(v);

    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: { labels: revenue.labels, datasets: [{ label: 'Pendapatan', data: revenue.values, backgroundColor: '#0d6efd', borderRadius: 4 }] },
        options: {
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => rupiah(c.parsed.y) } } },
            scales: { y: { beginAtZero: true, ticks: { callback: rupiah } } }
        }
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: { labels: status.labels, datasets: [{ data: status.values, backgroundColor: ['#ffc107', '#0dcaf0', '#198754', '#dc3545'] }] },
        options: { plugins: { legend: { position: 'bottom' } } }
    });
</script>
@endpush