@extends('admin.layouts.app')

@section('title', 'Banner')
@section('page_title', 'Banner Home')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="text-secondary small">Banner tampil sebagai carousel di Home aplikasi, diurutkan dari angka urutan terkecil.</div>
    <a href="{{ route('admin.banners.create') }}" class="btn btn-success"><i class="bi bi-plus-lg"></i> Tambah Banner</a>
</div>

<div class="row g-3">
    @forelse ($banners as $banner)
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" class="card-img-top" style="aspect-ratio:12/5;object-fit:cover">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="fw-semibold">{{ $banner->title }}</div>
                        <span class="badge {{ $banner->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $banner->is_active ? 'Tampil' : 'Disembunyikan' }}</span>
                    </div>
                    <div class="small text-secondary">Urutan: {{ $banner->sort_order }}</div>
                </div>
                <div class="card-footer bg-white d-flex gap-1">
                    <a href="{{ route('admin.banners.edit', $banner->id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Ubah</a>
                    <form method="POST" action="{{ route('admin.banners.toggle', $banner->id) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi {{ $banner->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i></button>
                    </form>
                    <form method="POST" action="{{ route('admin.banners.destroy', $banner->id) }}" onsubmit="return confirm('Hapus banner ini?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="card card-body border-0 shadow-sm text-center text-secondary py-5">Belum ada banner.</div></div>
    @endforelse
</div>
@endsection