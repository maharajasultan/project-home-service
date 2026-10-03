@extends('admin.layouts.app')

@section('title', 'Laporan Inventory')
@section('page_title', 'Laporan Inventory')

@section('content')
@php use App\Support\Format; @endphp

@include('admin.reports.partials.period', [
    'route' => 'admin.reports.inventory',
    'exportRoute' => 'admin.reports.inventory.export',
    'extra' => [
        'product' => $f['product'],
        'tab' => $f['tab'],
        'move' => $f['tab'] === 'log' && $f['move'] !== 'all' ? $f['move'] : null,
        'activity' => $f['tab'] === 'recap' && $f['activity'] !== 'all' ? $f['activity'] : null,
    ],
])

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Barang Masuk</div>
        <div class="fs-4 fw-semibold text-success">{{ number_format($summary['in'], 0, ',', '.') }}</div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Barang Keluar</div>
        <div class="fs-4 fw-semibold text-danger">{{ number_format($summary['sold'] + $summary['adjust'], 0, ',', '.') }}</div>
        <div class="small text-secondary">Terjual {{ $summary['sold'] }} · Koreksi {{ $summary['adjust'] }}</div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Stok Sistem Saat Ini</div>
        <div class="fs-4 fw-semibold">{{ number_format($summary['stock_now'], 0, ',', '.') }}</div>
        <div class="small text-secondary">Sudah dikurangi pesanan yang belum dibayar</div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Varian Stok Menipis</div>
        <div class="fs-4 fw-semibold {{ $summary['low_stock'] > 0 ? 'text-danger' : '' }}">{{ $summary['low_stock'] }}</div>
        <div class="small text-secondary">Stok 3 atau kurang</div>
    </div></div></div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <ul class="nav nav-pills gap-1">
                @foreach (['recap' => 'Rekap per Varian', 'log' => 'Riwayat Pergerakan'] as $key => $label)
                    <li class="nav-item">
                        <a class="nav-link {{ $f['tab'] === $key ? 'active' : 'bg-white border' }}"
                           href="{{ route('admin.reports.inventory', array_filter(['from' => $period->fromDate(), 'to' => $period->toDate(), 'product' => $f['product'], 'tab' => $key])) }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>

            <form method="GET" class="d-flex flex-wrap gap-2">
                <input type="hidden" name="from" value="{{ $period->fromDate() }}">
                <input type="hidden" name="to" value="{{ $period->toDate() }}">
                <input type="hidden" name="tab" value="{{ $f['tab'] }}">
                <select name="product" class="form-select form-select-sm" style="width:180px" onchange="this.form.submit()">
                    <option value="">Semua produk</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected($f['product'] === $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
                @if ($f['tab'] === 'recap')
                    <select name="activity" class="form-select form-select-sm" style="width:200px" onchange="this.form.submit()">
                        <option value="all" @selected($f['activity'] === 'all')>Semua varian</option>
                        <option value="moved" @selected($f['activity'] === 'moved')>Yang ada pergerakan saja</option>
                    </select>
                @else
                    <select name="move" class="form-select form-select-sm" style="width:150px" onchange="this.form.submit()">
                        <option value="all" @selected($f['move'] === 'all')>Masuk dan keluar</option>
                        <option value="in" @selected($f['move'] === 'in')>Masuk saja</option>
                        <option value="out" @selected($f['move'] === 'out')>Keluar saja</option>
                    </select>
                @endif
            </form>
        </div>
    </div>

    @if ($f['tab'] === 'recap')
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>Produk</th><th>Varian</th>
                    <th class="text-end">Stok Awal</th><th class="text-end">Masuk</th><th class="text-end">Terjual</th>
                    <th class="text-end">Koreksi</th><th class="text-end">Stok Akhir</th><th class="text-end">Stok Sistem</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($recap as $r)
                    @php $reserved = $r['closing'] - $r['current']; @endphp
                    <tr class="{{ $r['active'] ? '' : 'text-secondary' }}">
                        <td class="fw-semibold">{{ $r['name'] }}</td>
                        <td>{{ $r['label'] }} @unless ($r['active'])<span class="badge text-bg-secondary">nonaktif</span>@endunless</td>
                        <td class="text-end">{{ $r['opening'] }}</td>
                        <td class="text-end text-success">{{ $r['in'] ?: '-' }}</td>
                        <td class="text-end text-danger">{{ $r['sold'] ?: '-' }}</td>
                        <td class="text-end text-danger">{{ $r['adjust'] ?: '-' }}</td>
                        <td class="text-end fw-semibold">{{ $r['closing'] }}</td>
                        <td class="text-end {{ $r['current'] <= 3 ? 'text-danger fw-bold' : '' }}">
                            {{ $r['current'] }}
                            @if ($reserved > 0)
                                <span class="text-secondary small" title="Dipesan, menunggu pembayaran">(-{{ $reserved }} dipesan)</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-5">Tidak ada data pada periode ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-secondary">
            Stok Akhir = Stok Awal + Masuk - Terjual - Koreksi (berdasarkan catatan pergerakan). Stok Sistem adalah angka yang dilihat pelanggan; selisihnya adalah barang yang sedang dipesan tetapi belum dibayar.
        </div>
        @if ($variants && $variants->hasPages())
            <div class="card-footer bg-white">{{ $variants->links() }}</div>
        @endif
    @else
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                <tr><th>Waktu</th><th>Produk / Varian</th><th>Jenis</th><th class="text-end">Jumlah</th><th>Keterangan</th><th>Dicatat Oleh</th></tr>
                </thead>
                <tbody>
                @forelse ($log as $m)
                    <tr>
                        <td class="small text-secondary text-nowrap">{{ Format::dateTime($m->created_at) }}</td>
                        <td>{{ $m->variant?->product?->name ?? '-' }}<div class="small text-secondary">{{ $m->variant?->label() }}</div></td>
                        <td>
                            @if ($m->type === 'in')
                                <span class="badge text-bg-success">Masuk</span>
                            @elseif ($m->order_id)
                                <span class="badge text-bg-primary">Terjual</span>
                            @else
                                <span class="badge text-bg-danger">Koreksi</span>
                            @endif
                        </td>
                        <td class="text-end fw-semibold">{{ $m->type === 'in' ? '+' : '-' }}{{ $m->qty }}</td>
                        <td class="small">
                            {{ $m->note }}
                            @if ($m->order)
                                <a href="{{ route('admin.transactions.show', $m->order_id) }}" class="ms-1">{{ $m->order->order_code }}</a>
                            @endif
                        </td>
                        <td class="small">{{ $m->creator?->name ?? 'Sistem' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-5">Tidak ada pergerakan stok pada periode ini.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($log && $log->hasPages())
            <div class="card-footer bg-white">{{ $log->links() }}</div>
        @endif
    @endif
</div>
@endsection