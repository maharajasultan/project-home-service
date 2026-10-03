@extends('admin.layouts.app')

@section('title', 'Klaim Garansi')
@section('page_title', 'Klaim Garansi')

@section('content')
@php use App\Support\Format; @endphp

<ul class="nav nav-pills mb-3 flex-wrap gap-1">
    @foreach ($tabs as $key => $tab)
        <li class="nav-item">
            <a class="nav-link {{ $current === $key ? 'active' : 'bg-white border' }}" href="{{ route('admin.warranty.index', ['status' => $key]) }}">
                {{ $tab['label'] }} <span class="badge text-bg-light ms-1">{{ $tab['count'] }}</span>
            </a>
        </li>
    @endforeach
</ul>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead class="table-light"><tr><th>Pesanan</th><th>Pelanggan</th><th>Masalah</th><th>Garansi s/d</th><th>Status</th><th>Diajukan</th><th></th></tr></thead>
            <tbody>
            @forelse ($claims as $claim)
                <tr>
                    <td><a href="{{ route('admin.transactions.show', $claim->order_id) }}">{{ $claim->order?->order_code }}</a></td>
                    <td>{{ $claim->user?->name }}<div class="small text-secondary">{{ $claim->user?->phone }}</div></td>
                    <td style="max-width: 320px;">{{ $claim->reason }}
                        @if ($claim->admin_note)<div class="small text-secondary">Catatan: {{ $claim->admin_note }}</div>@endif
                    </td>
                    <td>{{ Format::dateTime($claim->order?->warrantyValidUntil(), 'd-m-Y') }}</td>
                    <td><span class="badge {{ Format::claimBadge($claim->status) }}">{{ Format::claimLabel($claim->status) }}</span></td>
                    <td class="small text-secondary">{{ Format::dateTime($claim->created_at) }}</td>
                    <td class="text-end">
                        @if ($claim->status === 'pending')
                            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#claim{{ $claim->id }}">Tinjau</button>
                        @endif
                    </td>
                </tr>
                @if ($claim->status === 'pending')
                    <tr class="collapse" id="claim{{ $claim->id }}">
                        <td colspan="7" class="bg-white">@include('admin.partials.claim-form', ['claim' => $claim])</td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-5">Tidak ada klaim.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($claims->hasPages())
        <div class="card-footer bg-white">{{ $claims->links() }}</div>
    @endif
</div>
@endsection