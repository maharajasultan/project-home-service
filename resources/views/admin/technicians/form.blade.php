@extends('admin.layouts.app')

@php $isEdit = $account !== null; @endphp

@section('title', $isEdit ? 'Ubah Teknisi' : 'Tambah Teknisi')
@section('page_title', $isEdit ? 'Ubah Teknisi' : 'Tambah Teknisi')

@section('content')
<div class="card border-0 shadow-sm" style="max-width: 860px;">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" novalidate
              action="{{ $isEdit ? route('admin.technicians.update', $account->id) : route('admin.technicians.store') }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            @include('admin.partials.account-fields', ['account' => $account, 'phoneRequired' => true])

            <div class="mt-3">
                <label for="bio" class="form-label">Bio / Keahlian (opsional)</label>
                <textarea id="bio" name="bio" rows="3" maxlength="500"
                          class="form-control @error('bio') is-invalid @enderror">{{ old('bio', $account?->technicianProfile?->bio) }}</textarea>
                @error('bio')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Simpan</button>
                <a href="{{ route('admin.technicians.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection