@extends('admin.layouts.app')

@section('title', 'Teknisi')
@section('page_title', 'Data Teknisi')

@section('content')
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
    <form method="GET" class="d-flex flex-wrap gap-2">
        <input type="text" name="q" value="{{ $term }}" class="form-control" style="width:260px" placeholder="Cari nama, email, atau HP">
        <select name="status" class="form-select" style="width:150px">
            <option value="all" @selected($status === 'all')>Semua status</option>
            <option value="active" @selected($status === 'active')>Aktif</option>
            <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
        </select>
        <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
    </form>
    <a href="{{ route('admin.technicians.create') }}" class="btn btn-success"><i class="bi bi-plus-lg"></i> Tambah Teknisi</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
            <tr><th>Nama</th><th>Kontak</th><th>Rating</th><th class="text-center">Aktif</th><th class="text-center">Selesai</th><th>Status</th><th class="text-end">Aksi</th></tr>
            </thead>
            <tbody>
            @forelse ($technicians as $tech)
                <tr>
                    <td class="fw-semibold">{{ $tech->name }}</td>
                    <td>{{ $tech->email }}<div class="small text-secondary">{{ $tech->phone }}</div></td>
                    <td class="text-warning text-nowrap">★ {{ number_format((float) ($tech->technicianProfile?->avg_rating ?? 0), 1) }}
                        <span class="text-secondary small">({{ $tech->technicianProfile?->rating_count ?? 0 }})</span></td>
                    <td class="text-center">{{ $tech->jobs_active }}</td>
                    <td class="text-center">{{ $tech->jobs_completed }}</td>
                    <td><span class="badge {{ $tech->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $tech->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.technicians.edit', $tech->id) }}" class="btn btn-sm btn-outline-primary" title="Ubah"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('admin.technicians.toggle', $tech->id) }}" class="d-inline"
                              onsubmit="return confirm('{{ $tech->is_active ? 'Nonaktifkan' : 'Aktifkan' }} teknisi ini?')">
                            @csrf
                            <button class="btn btn-sm btn-outline-warning" type="submit" title="{{ $tech->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                <i class="bi {{ $tech->is_active ? 'bi-person-slash' : 'bi-person-check' }}"></i>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.technicians.destroy', $tech->id) }}" class="d-inline"
                              onsubmit="return confirm('Hapus teknisi ini secara permanen?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Hapus"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-5">Tidak ada teknisi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($technicians->hasPages())
        <div class="card-footer bg-white">{{ $technicians->links() }}</div>
    @endif
</div>
@endsection