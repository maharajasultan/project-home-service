@extends('admin.layouts.app')

@section('title', 'Barang & Stok')
@section('page_title', 'Barang & Stok')

@section('content')
@php use App\Support\Format; use App\Enums\ProductType; @endphp

<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
    <form method="GET" class="d-flex flex-wrap gap-2">
        <input type="text" name="q" value="{{ $term }}" class="form-control" style="width:220px" placeholder="Cari nama produk">
        <select name="type" class="form-select" style="width:170px">
            <option value="">Semua jenis</option>
            @foreach (ProductType::cases() as $case)
                <option value="{{ $case->value }}" @selected($type === $case->value)>{{ $case->label() }}</option>
            @endforeach
        </select>
        <select name="status" class="form-select" style="width:140px">
            <option value="all" @selected($status === 'all')>Semua status</option>
            <option value="active" @selected($status === 'active')>Tampil</option>
            <option value="inactive" @selected($status === 'inactive')>Disembunyikan</option>
        </select>
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
    </form>
    <a href="{{ route('admin.products.create') }}" class="btn btn-success"><i class="bi bi-plus-lg"></i> Tambah Produk</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
            <tr><th></th><th>Produk</th><th>Jenis</th><th class="text-center">Varian</th><th>Harga</th><th class="text-center">Stok</th><th>Status</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
            @forelse ($products as $product)
                @php $thumb = $product->image_urls[0] ?? null; @endphp
                <tr>
                    <td style="width:64px">
                        @if ($thumb)
                            <img src="{{ $thumb }}" alt="" class="rounded" style="width:48px;height:48px;object-fit:cover">
                        @else
                            <div class="bg-light rounded d-flex align-items-center justify-content-center text-secondary" style="width:48px;height:48px"><i class="bi bi-image"></i></div>
                        @endif
                    </td>
                    <td class="fw-semibold">{{ $product->name }}</td>
                    <td><span class="badge text-bg-light border">{{ $product->type->label() }}</span></td>
                    <td class="text-center">{{ $product->variants_count }}</td>
                    <td class="text-nowrap">
                        @if ($product->type === ProductType::Matot)
                            <span class="small text-secondary">Biaya cek</span> {{ Format::rupiah($product->base_fee) }}
                        @elseif ($product->min_price !== null)
                            {{ Format::rupiah($product->min_price + $product->base_fee) }}
                            @if ((int) $product->max_price !== (int) $product->min_price)
                                <span class="text-secondary">–</span> {{ Format::rupiah($product->max_price + $product->base_fee) }}
                            @endif
                        @else
                            <span class="text-secondary">Belum ada varian</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if ($product->type === ProductType::Sparepart)
                            {{ (int) $product->total_stock }}
                            @if ($product->low_stock > 0)
                                <span class="badge text-bg-danger" title="Varian dengan stok 3 atau kurang">{{ $product->low_stock }} menipis</span>
                            @endif
                        @else
                            <span class="text-secondary">–</span>
                        @endif
                    </td>
                    <td><span class="badge {{ $product->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $product->is_active ? 'Tampil' : 'Disembunyikan' }}</span></td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.products.variants', $product->id) }}" class="btn btn-sm btn-primary"><i class="bi bi-boxes"></i> Varian & Stok</a>
                        <a href="{{ route('admin.products.edit', $product->id) }}" class="btn btn-sm btn-outline-primary" title="Ubah"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('admin.products.toggle', $product->id) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-outline-warning" type="submit" title="{{ $product->is_active ? 'Sembunyikan' : 'Tampilkan' }}">
                                <i class="bi {{ $product->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.products.destroy', $product->id) }}" class="d-inline"
                              onsubmit="return confirm('Hapus produk ini beserta semua variannya?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Hapus"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-secondary py-5">Tidak ada produk.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($products->hasPages())
        <div class="card-footer bg-white">{{ $products->links() }}</div>
    @endif
</div>
@endsection