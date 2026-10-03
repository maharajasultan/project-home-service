@extends('admin.layouts.app')

@section('title', 'Pelanggan')
@section('page_title', 'Data Pelanggan')

@section('content')
@php use App\Support\Format; @endphp

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
    <a href="{{ route('admin.users.create') }}" class="btn btn-success"><i class="bi bi-plus-lg"></i> Tambah Pelanggan</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Nama</th><th>Kontak</th><th class="text-center">Pesanan</th><th>Status</th><th>Terdaftar</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            @forelse ($users as $user)
                <tr>
                    <td class="fw-semibold">{{ $user->name }}</td>
                    <td>{{ $user->email }}<div class="small text-secondary">{{ $user->phone ?? '-' }}</div></td>
                    <td class="text-center">{{ $user->orders_count }}</td>
                    <td>
                        <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                    </td>
                    <td class="small text-secondary">{{ Format::dateTime($user->created_at, 'd-m-Y') }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.users.edit', $user->id) }}" class="btn btn-sm btn-outline-primary" title="Ubah"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('admin.users.toggle', $user->id) }}" class="d-inline"
                              onsubmit="return confirm('{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }} akun ini?')">
                            @csrf
                            <button class="btn btn-sm btn-outline-warning" type="submit" title="{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                <i class="bi {{ $user->is_active ? 'bi-person-slash' : 'bi-person-check' }}"></i>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}" class="d-inline"
                              onsubmit="return confirm('Hapus pelanggan ini secara permanen?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Hapus"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-secondary py-5">Tidak ada pelanggan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($users->hasPages())
        <div class="card-footer bg-white">{{ $users->links() }}</div>
    @endif
</div>
@endsection