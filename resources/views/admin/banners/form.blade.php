@extends('admin.layouts.app')

@php $isEdit = $banner !== null; @endphp

@section('title', $isEdit ? 'Ubah Banner' : 'Tambah Banner')
@section('page_title', $isEdit ? 'Ubah Banner' : 'Tambah Banner')

@section('content')
<div class="card border-0 shadow-sm" style="max-width: 720px;">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" novalidate
              action="{{ $isEdit ? route('admin.banners.update', $banner->id) : route('admin.banners.store') }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="mb-3">
                <label for="title" class="form-label">Judul</label>
                <input type="text" id="title" name="title" value="{{ old('title', $banner->title ?? '') }}" class="form-control" maxlength="100" required>
            </div>

            <div class="mb-3">
                <label for="image" class="form-label">Gambar {{ $isEdit ? '(kosongkan jika tidak diganti)' : '' }}</label>
                <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp" class="form-control" {{ $isEdit ? '' : 'required' }}>
                <div class="form-text">Disarankan 1200x500 piksel (rasio 12:5), maksimal 3 MB.</div>
                @if ($isEdit)
                    <img src="{{ $banner->image_url }}" alt="Banner saat ini" class="img-thumbnail mt-2" style="max-width: 320px;">
                @endif
            </div>

            <div class="mb-3">
                <label for="sort_order" class="form-label">Urutan (opsional)</label>
                <input type="number" id="sort_order" name="sort_order" min="0" max="9999" value="{{ old('sort_order', $banner->sort_order ?? '') }}" class="form-control" style="max-width:160px">
                <div class="form-text">Angka kecil tampil lebih dulu. Kosongkan untuk ditaruh paling akhir.</div>
            </div>

            <input type="hidden" name="is_active" value="0">
            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                       {{ old('is_active', $banner->is_active ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">Tampilkan di aplikasi</label>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Simpan</button>
                <a href="{{ route('admin.banners.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection