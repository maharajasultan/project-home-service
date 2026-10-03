@extends('admin.layouts.app')

@php
    use App\Enums\ProductType;
    use App\Support\Format;

    $type = $product->type;
    $isSparepart = $type === ProductType::Sparepart;
    $isMatot = $type === ProductType::Matot;
    $isUnlock = $type === ProductType::UnlockImei;
@endphp

@section('title', 'Varian '.$product->name)
@section('page_title', 'Varian & Stok: '.$product->name)

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Kembali</a>
    <div class="d-flex align-items-center gap-2">
        <span class="badge text-bg-light border">{{ $type->label() }}</span>
        <a href="{{ route('admin.products.edit', $product->id) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Ubah Produk</a>
    </div>
</div>

@if ($isMatot)
    <div class="alert alert-info">
        Service Matot hanya dikenakan <strong>ongkir/pengecekan {{ Format::rupiah($product->base_fee) }}</strong> (diatur di menu Ubah Produk).
        Estimasi perbaikan tidak dihitung di awal. Di sini Anda hanya menentukan tipe iPhone yang dilayani.
    </div>
@endif

<div class="row g-3">
    {{-- ===== Tabel varian ===== --}}
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Daftar Varian ({{ $variants->count() }})</span>
                @if ($variants->isNotEmpty())
                    <button class="btn btn-primary btn-sm" type="submit" form="pricesForm"><i class="bi bi-check-lg"></i> Simpan {{ $isMatot ? 'Status' : 'Harga & Status' }}</button>
                @endif
            </div>

            <form id="pricesForm" method="POST" action="{{ route('admin.products.variants.prices', $product->id) }}">
                @csrf
                @method('PATCH')
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>{{ $isUnlock ? 'Durasi' : 'Tipe iPhone' }}</th>
                            @if ($isSparepart)<th>Grade</th>@endif
                            @unless ($isMatot)<th style="width:170px">Harga (Rp)</th>@endunless
                            @if ($isSparepart)<th class="text-center" style="width:130px">Stok</th>@endif
                            <th class="text-center">Aktif</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($variants as $v)
                            <tr>
                                <td class="fw-semibold">{{ $isUnlock ? $v->duration_months.' Bulan' : ($v->iphoneModel?->name ?? '-') }}</td>
                                @if ($isSparepart)
                                    <td><span class="badge text-bg-secondary">{{ ucfirst((string) $v->grade) }}</span></td>
                                @endif
                                @unless ($isMatot)
                                    <td>
                                        <input type="number" min="0" step="1" name="variants[{{ $v->id }}][price]" value="{{ $v->price }}" class="form-control form-control-sm">
                                    </td>
                                @endunless
                                @if ($isSparepart)
                                    <td class="text-center text-nowrap">
                                        <span class="{{ $v->stock <= 3 ? 'text-danger fw-bold' : '' }}">{{ $v->stock }}</span>
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-stock ms-1"
                                                data-action="{{ route('admin.products.variants.stock', [$product->id, $v->id]) }}"
                                                data-name="{{ $v->label() }}" data-stock="{{ $v->stock }}">Stok</button>
                                    </td>
                                @endif
                                <td class="text-center">
                                    <input type="hidden" name="variants[{{ $v->id }}][is_active]" value="0">
                                    <div class="form-check form-switch d-inline-block m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="variants[{{ $v->id }}][is_active]" value="1" @checked($v->is_active)>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <button type="submit" form="vdel{{ $v->id }}" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-5">Belum ada varian. Tambahkan lewat form di sebelah kanan.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </form>

            @foreach ($variants as $v)
                <form id="vdel{{ $v->id }}" method="POST" class="d-none"
                      action="{{ route('admin.products.variants.destroy', [$product->id, $v->id]) }}"
                      onsubmit="return confirm('Hapus varian ini?')">
                    @csrf
                    @method('DELETE')
                </form>
            @endforeach
        </div>

        @if ($isSparepart)
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">10 Pergerakan Stok Terakhir</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Waktu</th><th>Varian</th><th>Jenis</th><th class="text-center">Jumlah</th><th>Catatan</th></tr></thead>
                        <tbody>
                        @forelse ($movements as $m)
                            <tr>
                                <td class="small text-secondary text-nowrap">{{ Format::dateTime($m->created_at) }}</td>
                                <td>{{ $m->variant?->label() }}</td>
                                <td><span class="badge {{ $m->type === 'in' ? 'text-bg-success' : 'text-bg-danger' }}">{{ $m->type === 'in' ? 'Masuk' : 'Keluar' }}</span></td>
                                <td class="text-center">{{ $m->qty }}</td>
                                <td class="small">{{ $m->note }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-3">Belum ada pergerakan stok.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    {{-- ===== Form tambah varian ===== --}}
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Tambah Varian</div>
            <div class="card-body">
                @if ($isUnlock)
                    <form method="POST" action="{{ route('admin.products.variants.duration', $product->id) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="duration_months">Durasi (bulan)</label>
                            <input type="number" id="duration_months" name="duration_months" min="1" max="36" value="{{ old('duration_months') }}" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="price">Harga (Rp)</label>
                            <input type="number" id="price" name="price" min="0" step="1" value="{{ old('price') }}" class="form-control" required>
                        </div>
                        <button class="btn btn-success w-100" type="submit"><i class="bi bi-plus-lg"></i> Tambah Durasi</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.products.variants.generate', $product->id) }}">
                        @csrf
                        <div class="d-flex justify-content-between">
                            <label class="form-label">Tipe iPhone</label>
                            <a href="#" id="toggleModels" class="small">Pilih semua</a>
                        </div>
                        <div class="row row-cols-2 g-1 mb-3">
                            @foreach ($models as $model)
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input model-check" type="checkbox" name="models[]" value="{{ $model->id }}" id="m{{ $model->id }}">
                                        <label class="form-check-label small" for="m{{ $model->id }}">{{ $model->name }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($isSparepart)
                            <label class="form-label">Grade</label>
                            <div class="mb-3">
                                @foreach (\App\Models\ProductVariant::GRADES as $grade)
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="grades[]" value="{{ $grade }}" id="g{{ $grade }}">
                                        <label class="form-check-label" for="g{{ $grade }}">{{ ucfirst($grade) }}</label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="gprice">Harga awal (Rp)</label>
                                <input type="number" id="gprice" name="price" min="0" step="1" class="form-control" required>
                                <div class="form-text">Berlaku untuk semua kombinasi yang dipilih. Bisa disesuaikan per baris setelahnya.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="gstock">Stok awal (opsional)</label>
                                <input type="number" id="gstock" name="stock" min="0" value="0" class="form-control">
                            </div>
                        @endif

                        <button class="btn btn-success w-100" type="submit"><i class="bi bi-plus-lg"></i> Buat Varian</button>
                        <div class="form-text mt-2">Kombinasi yang sudah ada otomatis dilewati.</div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ===== Modal stok ===== --}}
@if ($isSparepart)
<div class="modal fade" id="stockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="stockForm" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="stockTitle">Atur Stok</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="type" id="stIn" value="in" checked>
                        <label class="form-check-label" for="stIn">Barang masuk (+)</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="type" id="stOut" value="out">
                        <label class="form-check-label" for="stOut">Koreksi / keluar (−)</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="stQty">Jumlah</label>
                    <input type="number" id="stQty" name="qty" min="1" max="10000" class="form-control" required>
                </div>
                <div class="mb-3" id="costGroup">
                    <label class="form-label" for="stCost">Total biaya pembelian (Rp, opsional)</label>
                    <input type="number" id="stCost" name="cost" min="0" step="1" class="form-control">
                    <div class="form-text">Jika diisi, otomatis tercatat sebagai uang keluar di laporan keuangan.</div>
                </div>
                <div class="mb-1">
                    <label class="form-label" for="stNote">Catatan</label>
                    <input type="text" id="stNote" name="note" maxlength="150" class="form-control" placeholder="Contoh: beli dari supplier A / barang rusak">
                    <div class="form-text" id="noteHint">Wajib diisi untuk koreksi stok.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    const toggleLink = document.getElementById('toggleModels');
    if (toggleLink) {
        toggleLink.addEventListener('click', (e) => {
            e.preventDefault();
            const boxes = document.querySelectorAll('.model-check');
            const allChecked = [...boxes].every((b) => b.checked);
            boxes.forEach((b) => (b.checked = !allChecked));
            toggleLink.textContent = allChecked ? 'Pilih semua' : 'Batalkan semua';
        });
    }

    const stockModalEl = document.getElementById('stockModal');
    if (stockModalEl) {
        const modal = new bootstrap.Modal(stockModalEl);
        const costGroup = document.getElementById('costGroup');
        const noteHint = document.getElementById('noteHint');

        document.querySelectorAll('.btn-stock').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.getElementById('stockForm').action = btn.dataset.action;
                document.getElementById('stockTitle').textContent = btn.dataset.name + ' (stok: ' + btn.dataset.stock + ')';
                document.getElementById('stIn').checked = true;
                costGroup.style.display = '';
                noteHint.style.display = 'none';
                modal.show();
            });
        });

        stockModalEl.querySelectorAll('input[name="type"]').forEach((radio) => {
            radio.addEventListener('change', () => {
                const isIn = document.getElementById('stIn').checked;
                costGroup.style.display = isIn ? '' : 'none';
                noteHint.style.display = isIn ? 'none' : '';
            });
        });

        noteHint.style.display = 'none';
    }
</script>
@endpush