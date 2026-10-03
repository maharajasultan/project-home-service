@extends('admin.layouts.app')

@section('title', 'Transaksi')
@section('page_title', 'Manajemen Transaksi')

@section('content')
@php use App\Support\Format; @endphp

<ul class="nav nav-pills mb-3 flex-wrap gap-1">
    @foreach ($tabs as $key => $tab)
        <li class="nav-item">
            <a class="nav-link {{ $current === $key ? 'active' : 'bg-white border' }}"
               href="{{ route('admin.transactions.index', array_filter(['tab' => $key === 'all' ? null : $key, 'q' => request('q'), 'from' => request('from'), 'to' => request('to')])) }}">
                {{ $tab['label'] }} <span class="badge text-bg-light ms-1">{{ $tab['count'] }}</span>
            </a>
        </li>
    @endforeach
</ul>

<form method="GET" class="card card-body border-0 shadow-sm mb-3">
    <input type="hidden" name="tab" value="{{ $current }}">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small mb-1">Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Kode pesanan, nama, email, atau nomor WA">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Dari tanggal</label>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Sampai</label>
            <input type="date" name="to" value="{{ request('to') }}" class="form-control">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-search"></i> Filter</button>
            <a href="{{ route('admin.transactions.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
            <tr><th>Kode</th><th>Pelanggan</th><th>Teknisi</th><th>Jadwal</th><th class="text-end">Total</th><th>Status</th><th>Dibuat</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td class="fw-semibold">{{ $order->order_code }}</td>
                    <td>{{ $order->user?->name ?? '-' }}<div class="small text-secondary">{{ $order->phone_wa }}</div></td>
                    <td>{{ $order->technician?->name ?? '-' }}</td>
                    <td>{{ Format::dateTime($order->scheduled_at) }}</td>
                    <td class="text-end">{{ Format::rupiah($order->total) }}</td>
                    <td><span class="badge {{ Format::statusBadge($order->status) }}">{{ Format::statusLabel($order->status) }}</span></td>
                    <td class="small text-secondary">{{ Format::dateTime($order->created_at) }}</td>
                    <td class="text-end"><a href="{{ route('admin.transactions.show', $order->id) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-secondary py-5">Tidak ada transaksi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($orders->hasPages())
        <div class="card-footer bg-white">{{ $orders->links() }}</div>
    @endif
</div>
@endsection