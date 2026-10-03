@extends('admin.layouts.app')

@php $isEdit = $account !== null; @endphp

@section('title', $isEdit ? 'Ubah Pelanggan' : 'Tambah Pelanggan')
@section('page_title', $isEdit ? 'Ubah Pelanggan' : 'Tambah Pelanggan')

@section('content')
<div class="card border-0 shadow-sm" style="max-width: 860px;">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" novalidate
              action="{{ $isEdit ? route('admin.users.update', $account->id) : route('admin.users.store') }}">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            @include('admin.partials.account-fields', ['account' => $account, 'phoneRequired' => false])

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Simpan</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection