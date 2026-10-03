@extends('admin.layouts.app')

@section('title', 'Ulasan')
@section('page_title', 'Manajemen Ulasan')

@section('content')
@php use App\Support\Format; @endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-secondary small">Rating Teknisi</div>
        <div class="fs-5 fw-semibold text-warning">★ {{ number_format($summary['technician']['avg'], 1) }} <span class="text-secondary fs-6">({{ $summary['technician']['total'] }})</span></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-secondary small">Rating Produk</div>
        <div class="fs-5 fw-semibold text-warning">★ {{ number_format($summary['product']['avg'], 1) }} <span class="text-secondary fs-6">({{ $summary['product']['total'] }})</span></div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-secondary small">Belum Dibalas</div>
        <div class="fs-5 fw-semibold">{{ $unreplied }}</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm"><div class="card-body">
        <div class="text-secondary small">Rating Rendah (1-2 ★)</div>
        <div class="fs-5 fw-semibold {{ $lowRating > 0 ? 'text-danger' : '' }}">{{ $lowRating }}</div>
    </div></div></div>
</div>

<ul class="nav nav-pills mb-3 gap-1">
    @foreach (['all' => 'Semua', 'technician' => 'Teknisi', 'product' => 'Produk'] as $key => $label)
        <li class="nav-item">
            <a class="nav-link {{ $target === $key ? 'active' : 'bg-white border' }}"
               href="{{ route('admin.reviews.index', array_filter(['target' => $key === 'all' ? null : $key, 'rating' => $rating, 'status' => $status === 'all' ? null : $status, 'q' => $term])) }}">{{ $label }}</a>
        </li>
    @endforeach
</ul>

<form method="GET" class="card card-body border-0 shadow-sm mb-3">
    <input type="hidden" name="target" value="{{ $target }}">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small mb-1">Cari komentar atau nama pelanggan</label>
            <input type="text" name="q" value="{{ $term }}" class="form-control">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Rating</label>
            <select name="rating" class="form-select">
                <option value="">Semua</option>
                @foreach ([5, 4, 3, 2, 1] as $star)
                    <option value="{{ $star }}" @selected((string) $rating === (string) $star)>{{ $star }} ★</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1">Balasan</label>
            <select name="status" class="form-select">
                <option value="all" @selected($status === 'all')>Semua</option>
                <option value="unreplied" @selected($status === 'unreplied')>Belum dibalas</option>
                <option value="replied" @selected($status === 'replied')>Sudah dibalas</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit"><i class="bi bi-search"></i> Filter</button>
            <a href="{{ route('admin.reviews.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead class="table-light"><tr><th>Untuk</th><th>Pelanggan</th><th>Rating</th><th>Komentar</th><th>Waktu</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            @forelse ($reviews as $review)
                <tr>
                    <td>
                        <span class="badge {{ $review->target_type === 'technician' ? 'text-bg-info' : 'text-bg-secondary' }}">{{ $review->target_type === 'technician' ? 'Teknisi' : 'Produk' }}</span>
                        <div class="fw-semibold">{{ $review->target_type === 'technician' ? ($techNames[$review->target_id] ?? '#'.$review->target_id) : ($productNames[$review->target_id] ?? '#'.$review->target_id) }}</div>
                    </td>
                    <td>{{ $review->user?->name ?? '-' }}
                        @if ($review->order)<div class="small"><a href="{{ route('admin.transactions.show', $review->order_id) }}">{{ $review->order->order_code }}</a></div>@endif
                    </td>
                    <td class="text-nowrap {{ $review->rating <= 2 ? 'text-danger' : 'text-warning' }}">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</td>
                    <td style="max-width: 340px;">
                        {{ $review->comment ?: '(tanpa komentar)' }}
                        @if ($review->reply)
                            <div class="small text-secondary mt-1"><i class="bi bi-reply"></i> Balasan: {{ $review->reply }}</div>
                        @endif
                    </td>
                    <td class="small text-secondary text-nowrap">{{ Format::dateTime($review->created_at) }}</td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#reply{{ $review->id }}">
                            <i class="bi bi-reply"></i> {{ $review->reply ? 'Ubah balasan' : 'Balas' }}
                        </button>
                        <form method="POST" action="{{ route('admin.reviews.destroy', $review->id) }}" class="d-inline"
                              onsubmit="return confirm('Hapus ulasan ini? Rating akan dihitung ulang.')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Hapus"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <tr class="collapse" id="reply{{ $review->id }}">
                    <td colspan="6" class="bg-light">
                        <form method="POST" action="{{ route('admin.reviews.reply', $review->id) }}" class="d-flex gap-2">
                            @csrf
                            @method('PATCH')
                            <input type="text" name="reply" maxlength="500" value="{{ $review->reply }}" class="form-control" placeholder="Tulis balasan sebagai admin" required>
                            <button class="btn btn-primary text-nowrap" type="submit">Kirim</button>
                        </form>
                        @if ($review->reply)<div class="form-text">Balasan lama (misalnya dari teknisi) akan tertimpa.</div>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-5">Tidak ada ulasan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($reviews->hasPages())
        <div class="card-footer bg-white">{{ $reviews->links() }}</div>
    @endif
</div>
@endsection