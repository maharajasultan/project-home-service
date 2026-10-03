@extends('admin.layouts.app')

@section('title', 'Laporan Keuangan')
@section('page_title', 'Laporan Keuangan')

@section('content')
@php
    use App\Models\FinanceTransaction as FT;
    use App\Support\Format;

    $inBreakdown = $breakdown->where('type', 'in');
    $outBreakdown = $breakdown->where('type', 'out');
@endphp

@include('admin.reports.partials.period', [
    'route' => 'admin.reports.finance',
    'exportRoute' => 'admin.reports.finance.export',
    'extra' => ['type' => $type === 'all' ? null : $type, 'category' => $category],
])

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Uang Masuk</div>
        <div class="fs-5 fw-semibold text-success">{{ Format::rupiah($summary['in']) }}</div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Uang Keluar</div>
        <div class="fs-5 fw-semibold text-danger">{{ Format::rupiah($summary['out']) }}</div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Arus Kas Bersih</div>
        <div class="fs-5 fw-semibold {{ $summary['net'] < 0 ? 'text-danger' : '' }}">{{ ($summary['net'] < 0 ? '-' : '').Format::rupiah(abs($summary['net'])) }}</div>
        <div class="small text-secondary">Masuk dikurangi keluar</div>
    </div></div></div>
    <div class="col-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
        <div class="text-secondary small">Jumlah Transaksi</div>
        <div class="fs-5 fw-semibold">{{ $summary['count'] }}</div>
    </div></div></div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">Grafik {{ $chart['monthly'] ? 'Bulanan' : 'Harian' }}</div>
    <div class="card-body"><canvas id="financeChart" height="90"></canvas></div>
</div>

<div class="row g-3 mb-3">
    @foreach ([['Rincian Uang Masuk', $inBreakdown, 'text-success'], ['Rincian Uang Keluar', $outBreakdown, 'text-danger']] as [$title, $items, $color])
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">{{ $title }}</div>
                <table class="table table-sm mb-0">
                    <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td>{{ FT::CATEGORY_LABELS[$item->category] ?? 'Tanpa kategori' }} <span class="text-secondary small">({{ $item->cnt }}x)</span></td>
                            <td class="text-end {{ $color }}">{{ Format::rupiah($item->total) }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-3">Tidak ada data.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="fw-semibold">Daftar Transaksi</span>
        <div class="d-flex flex-wrap gap-2">
            <form method="GET" class="d-flex flex-wrap gap-2">
                <input type="hidden" name="from" value="{{ $period->fromDate() }}">
                <input type="hidden" name="to" value="{{ $period->toDate() }}">
                <select name="type" class="form-select form-select-sm" style="width:140px" onchange="this.form.submit()">
                    <option value="all" @selected($type === 'all')>Semua jenis</option>
                    <option value="in" @selected($type === 'in')>Uang masuk</option>
                    <option value="out" @selected($type === 'out')>Uang keluar</option>
                </select>
                <select name="category" class="form-select form-select-sm" style="width:190px" onchange="this.form.submit()">
                    <option value="">Semua kategori</option>
                    @foreach (FT::CATEGORY_LABELS as $key => $label)
                        <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
            <button class="btn btn-sm btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#expenseModal">
                <i class="bi bi-plus-lg"></i> Catat Pengeluaran
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
            <tr><th>Tanggal</th><th>Jenis</th><th>Kategori</th><th>Keterangan</th><th class="text-end">Masuk</th><th class="text-end">Keluar</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($transactions as $t)
                <tr>
                    <td class="text-nowrap">{{ Format::dateTime($t->transaction_date, 'd-m-Y') }}</td>
                    <td><span class="badge {{ $t->type === 'in' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $t->type === 'in' ? 'Masuk' : 'Keluar' }}</span></td>
                    <td>{{ FT::CATEGORY_LABELS[$t->category] ?? '-' }}</td>
                    <td style="max-width:360px">
                        {{ $t->description }}
                        @if ($t->order)
                            <a href="{{ route('admin.transactions.show', $t->order_id) }}" class="small ms-1">{{ $t->order->order_code }}</a>
                        @endif
                        @if ($t->isManual() && $t->creator)
                            <div class="small text-secondary">Dicatat oleh {{ $t->creator->name }}</div>
                        @endif
                    </td>
                    <td class="text-end text-success">{{ $t->type === 'in' ? Format::rupiah($t->amount) : '' }}</td>
                    <td class="text-end text-danger">{{ $t->type === 'out' ? Format::rupiah($t->amount) : '' }}</td>
                    <td class="text-end">
                        @if ($t->isManual())
                            <form method="POST" action="{{ route('admin.reports.finance.expenses.destroy', $t->id) }}"
                                  onsubmit="return confirm('Hapus pengeluaran ini?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="Hapus"><i class="bi bi-trash"></i></button>
                            </form>
                        @else
                            <i class="bi bi-lock text-secondary" title="Transaksi otomatis, tidak dapat dihapus"></i>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-5">Tidak ada transaksi pada periode ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($transactions->hasPages())
        <div class="card-footer bg-white">{{ $transactions->links() }}</div>
    @endif
</div>

{{-- ===== Modal catat pengeluaran ===== --}}
<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.reports.finance.expenses.store') }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Catat Pengeluaran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="exDate">Tanggal</label>
                    <input type="date" id="exDate" name="transaction_date" value="{{ old('transaction_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="exCategory">Kategori</label>
                    <select id="exCategory" name="category" class="form-select" required>
                        <option value="">Pilih kategori</option>
                        @foreach (FT::EXPENSE_CATEGORIES as $key => $label)
                            <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Pembelian stok dicatat otomatis dari menu Barang & Stok (Barang masuk).</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="exAmount">Nominal (Rp)</label>
                    <input type="number" id="exAmount" name="amount" min="1" step="1" value="{{ old('amount') }}" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="exDesc">Keterangan</label>
                    <input type="text" id="exDesc" name="description" maxlength="190" value="{{ old('description') }}" class="form-control" placeholder="Contoh: bonus teknisi Budi bulan Oktober" required>
                </div>
                <div class="mb-1">
                    <label class="form-label" for="exOrder">Kode pesanan (khusus Refund)</label>
                    <input type="text" id="exOrder" name="order_code" maxlength="30" value="{{ old('order_code') }}" class="form-control" placeholder="RPL-261003-ABCDE">
                    <div class="form-text">Isi saat mencatat refund dari alert "Perlu refund manual" di detail transaksi.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    const chart = @json($chart);
    const rupiah = (v) => 'Rp' + new Intl.NumberFormat('id-ID').format(v);

    new Chart(document.getElementById('financeChart'), {
        type: 'bar',
        data: {
            labels: chart.labels,
            datasets: [
                { label: 'Uang Masuk', data: chart.in, backgroundColor: '#198754', borderRadius: 4 },
                { label: 'Uang Keluar', data: chart.out, backgroundColor: '#dc3545', borderRadius: 4 },
            ],
        },
        options: {
            plugins: { tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + rupiah(c.parsed.y) } } },
            scales: { y: { beginAtZero: true, ticks: { callback: rupiah } } },
        },
    });

    @if ($errors->any() && old('amount') !== null)
        new bootstrap.Modal(document.getElementById('expenseModal')).show();
    @endif
</script>
@endpush