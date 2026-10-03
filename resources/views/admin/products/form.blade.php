@extends('admin.layouts.app')

@php
    use App\Enums\ProductType;
    use App\Support\MediaUrl;
    $isEdit = $product !== null;
    $images = $product?->images ?? [];
@endphp

@section('title', $isEdit ? 'Ubah Produk' : 'Tambah Produk')
@section('page_title', $isEdit ? 'Ubah Produk' : 'Tambah Produk')

@section('content')
<div class="card border-0 shadow-sm" style="max-width: 860px;">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" novalidate
              action="{{ $isEdit ? route('admin.products.update', $product->id) : route('admin.products.store') }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="row g-3">
                <div class="col-md-8">
                    <label for="name" class="form-label">Nama Produk</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $product->name ?? '') }}" class="form-control" maxlength="100" required>
                </div>
                <div class="col-md-4">
                    <label for="type" class="form-label">Jenis</label>
                    @if ($isEdit)
                        <input type="text" class="form-control" value="{{ $product->type->label() }}" disabled>
                        <div class="form-text">Jenis tidak dapat diubah.</div>
                    @else
                        <select id="type" name="type" class="form-select" required>
                            <option value="">Pilih jenis</option>
                            @foreach (ProductType::cases() as $case)
                                <option value="{{ $case->value }}" @selected(old('type') === $case->value)>{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">Deskripsi</label>
                    <textarea id="description" name="description" rows="4" maxlength="2000" class="form-control">{{ old('description', $product->description ?? '') }}</textarea>
                </div>

                <div class="col-md-6">
                    <label for="base_fee" class="form-label">Ongkir / Biaya Pengecekan (Rp)</label>
                    <input type="number" id="base_fee" name="base_fee" min="0" step="1" value="{{ old('base_fee', $product->base_fee ?? 0) }}" class="form-control">
                    <div class="form-text">Wajib diisi untuk <strong>Service Matot</strong> (satu-satunya biaya di awal). Untuk sparepart dan Unlock IMEI biasanya 0.</div>
                </div>

                <div class="col-md-6 d-flex align-items-center">
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                               {{ old('is_active', $product->is_active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">Tampilkan di aplikasi</label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Foto Produk (maksimal 6)</label>
                    @if ($images)
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            @foreach ($images as $path)
                                <label class="border rounded p-1 text-center">
                                    <img src="{{ MediaUrl::resolve($path) }}" alt="" style="width:96px;height:96px;object-fit:cover" class="rounded d-block">
                                    <span class="form-check small mt-1 d-block">
                                        <input type="checkbox" class="form-check-input" name="remove_images[]" value="{{ $path }}"> Hapus
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <input type="file" name="images[]" multiple accept=".jpg,.jpeg,.png,.webp" class="form-control">
                    <div class="form-text">JPG, PNG, atau WEBP, maksimal 3 MB per foto. Foto pertama menjadi gambar utama.</div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> {{ $isEdit ? 'Simpan' : 'Simpan dan Atur Varian' }}</button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection