@extends('admin.layouts.app')

@section('title', $order->order_code)
@section('page_title', 'Detail Transaksi')

@section('content')
@php
    use App\Support\Format;
    use App\Support\MediaUrl;
    use App\Services\PaymentService;

    $payment = $order->payment;
    $before = $order->photos->where('type', 'before');
    $after = $order->photos->where('type', 'after');
    $wa = Format::whatsappUrl($order->phone_wa);
    $mapUrl = $order->latitude && $order->longitude
        ? "https://www.openstreetmap.org/?mlat={$order->latitude}&mlon={$order->longitude}#map=17/{$order->latitude}/{$order->longitude}"
        : null;
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a href="{{ route('admin.transactions.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    @if ($order->status->value === 'pending')
        <form method="POST" action="{{ route('admin.transactions.cancel', $order->id) }}"
              onsubmit="return confirm('Batalkan pesanan ini? Stok dan jadwal teknisi akan dikembalikan.')">
            @csrf
            <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-circle"></i> Batalkan Pesanan</button>
        </form>
    @endif
</div>

@if ($needsManualRefund)
    <div class="alert alert-danger">
        <strong><i class="bi bi-exclamation-octagon"></i> Perlu refund manual.</strong>
        Uang pelanggan sudah masuk di Midtrans ({{ Format::rupiah($payment->gross_amount) }}), tetapi pesanan ini berstatus gagal dan tidak bisa dihidupkan kembali
        (stok habis atau jadwal teknisi sudah terisi). Lakukan refund lewat dashboard Midtrans, lalu hubungi pelanggan.
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        {{-- Ringkasan --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <div class="text-secondary small">Kode Pesanan</div>
                        <div class="fs-5 fw-semibold">{{ $order->order_code }}</div>
                    </div>
                    <div><span class="badge fs-6 {{ Format::statusBadge($order->status) }}">{{ Format::statusLabel($order->status) }}</span></div>
                </div>
                <hr>
                <div class="row small g-2">
                    <div class="col-6 col-md-3"><div class="text-secondary">Dibuat</div>{{ Format::dateTime($order->created_at) }}</div>
                    <div class="col-6 col-md-3"><div class="text-secondary">Batas Bayar</div>{{ Format::dateTime($order->expired_at) }}</div>
                    <div class="col-6 col-md-3"><div class="text-secondary">Dibayar</div>{{ Format::dateTime($order->paid_at) }}</div>
                    <div class="col-6 col-md-3"><div class="text-secondary">Selesai</div>{{ Format::dateTime($order->completed_at) }}</div>
                </div>
            </div>
        </div>

        {{-- Item --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Item Pesanan</div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead class="table-light"><tr><th>Produk</th><th class="text-end">Harga</th><th class="text-end">Ongkir/Cek</th><th class="text-center">Qty</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>{{ $item->product_name }}<div class="small text-secondary">{{ $item->variant_label }}</div></td>
                            <td class="text-end">{{ Format::rupiah($item->price) }}</td>
                            <td class="text-end">{{ Format::rupiah($item->base_fee) }}</td>
                            <td class="text-center">{{ $item->qty }}</td>
                            <td class="text-end">{{ Format::rupiah(($item->price + $item->base_fee) * $item->qty) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td colspan="4" class="text-end text-secondary">Subtotal barang/jasa</td><td class="text-end">{{ Format::rupiah($order->subtotal) }}</td></tr>
                        <tr><td colspan="4" class="text-end text-secondary">Ongkir/pengecekan</td><td class="text-end">{{ Format::rupiah($order->service_fee) }}</td></tr>
                        <tr class="fw-bold"><td colspan="4" class="text-end">Total</td><td class="text-end">{{ Format::rupiah($order->total) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Foto --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Foto Service</div>
            <div class="card-body">
                @foreach (['Sebelum Service' => $before, 'Sesudah Service' => $after] as $title => $photos)
                    <div class="small text-secondary mb-1">{{ $title }}</div>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @forelse ($photos as $photo)
                            <a href="{{ $photo->url }}" target="_blank" rel="noopener">
                                <img src="{{ $photo->url }}" alt="{{ $title }}" class="img-thumbnail" style="width:110px;height:110px;object-fit:cover">
                            </a>
                        @empty
                            <span class="text-secondary small">Belum ada foto.</span>
                        @endforelse
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Ulasan --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Ulasan Pelanggan</div>
            <ul class="list-group list-group-flush">
                @forelse ($order->reviews as $review)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <strong>
                                {{ $review->target_type === 'technician' ? 'Teknisi' : 'Produk: '.($productNames[$review->target_id] ?? '#'.$review->target_id) }}
                            </strong>
                            <span class="text-warning">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                        </div>
                        <div>{{ $review->comment ?: '(tanpa komentar)' }}</div>
                        @if ($review->reply)
                            <div class="small text-secondary mt-1"><i class="bi bi-reply"></i> Balasan: {{ $review->reply }}</div>
                        @endif
                    </li>
                @empty
                    <li class="list-group-item text-secondary">Belum ada ulasan.</li>
                @endforelse
            </ul>
        </div>

        {{-- Garansi --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between">
                <span class="fw-semibold">Klaim Garansi</span>
                <span class="small text-secondary">
                    @if ($order->completed_at) Berlaku s/d {{ Format::dateTime($order->warrantyValidUntil(), 'd-m-Y') }} @else Belum selesai @endif
                </span>
            </div>
            <ul class="list-group list-group-flush">
                @forelse ($order->warrantyClaims->sortByDesc('id') as $claim)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <span class="badge {{ Format::claimBadge($claim->status) }}">{{ Format::claimLabel($claim->status) }}</span>
                            <span class="small text-secondary">{{ Format::dateTime($claim->created_at) }}</span>
                        </div>
                        <div class="mt-2">{{ $claim->reason }}</div>
                        @if ($claim->admin_note)
                            <div class="small text-secondary mt-1">Catatan admin: {{ $claim->admin_note }}</div>
                        @endif
                        @if ($claim->status === 'pending')
                            @include('admin.partials.claim-form', ['claim' => $claim])
                        @endif
                    </li>
                @empty
                    <li class="list-group-item text-secondary">Tidak ada klaim garansi.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- Pelanggan --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Pelanggan</div>
            <div class="card-body small">
                <div class="fw-semibold fs-6">{{ $order->user?->name ?? '-' }}</div>
                <div class="text-secondary mb-2">{{ $order->user?->email }}</div>
                <div class="text-secondary">WhatsApp</div>
                <div class="mb-2">
                    {{ $order->phone_wa }}
                    @if ($wa)<a href="{{ $wa }}" target="_blank" rel="noopener" class="ms-1"><i class="bi bi-whatsapp text-success"></i></a>@endif
                </div>
                <div class="text-secondary">Alamat</div>
                <div>{{ $order->address }}</div>
                <div class="text-secondary mt-1">Kecamatan: {{ $order->district ?? '-' }}</div>
                @if ($order->notes)<div class="mt-2"><span class="text-secondary">Catatan:</span> {{ $order->notes }}</div>@endif
                @if ($mapUrl)<a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="d-inline-block mt-2"><i class="bi bi-geo-alt"></i> Lihat di OpenStreetMap</a>@endif
            </div>
        </div>

        {{-- Jadwal dan teknisi --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Jadwal dan Teknisi</div>
            <div class="card-body small">
                <div class="text-secondary">Jadwal kunjungan</div>
                <div class="fw-semibold mb-2">{{ Format::dateTime($order->scheduled_at) }} WIB</div>
                <div class="text-secondary">Teknisi</div>
                @if ($order->technician)
                    <div class="fw-semibold">{{ $order->technician->name }}</div>
                    <div class="text-secondary">{{ $order->technician->phone }}</div>
                    <div class="text-warning">★ {{ number_format((float) ($order->technician->technicianProfile?->avg_rating ?? 0), 1) }}
                        <span class="text-secondary">({{ $order->technician->technicianProfile?->rating_count ?? 0 }} ulasan)</span></div>
                @else
                    <div>-</div>
                @endif
            </div>
        </div>

        {{-- Pembayaran --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Pembayaran</div>
            <div class="card-body small">
                @if ($payment)
                    <div class="d-flex justify-content-between"><span class="text-secondary">Metode</span><span>{{ $payment->method ? (PaymentService::METHOD_LABELS[$payment->method] ?? $payment->method) : 'Belum dipilih' }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-secondary">Status Midtrans</span><span>{{ $payment->status }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-secondary">Nominal</span><span>{{ Format::rupiah($payment->gross_amount) }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-secondary">Dibayar</span><span>{{ Format::dateTime($payment->paid_at) }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-secondary">ID Midtrans</span><span class="text-break">{{ $payment->midtrans_order_id }}</span></div>
                @else
                    <span class="text-secondary">Pelanggan belum membuka halaman pembayaran.</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection