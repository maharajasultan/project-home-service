<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WarrantyClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WarrantyClaimController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', 'in:all,pending,approved,rejected']]);
        $current = $filters['status'] ?? 'pending';

        $claims = WarrantyClaim::with(['order', 'user'])
            ->when($current !== 'all', fn ($q) => $q->where('status', $current))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $by = WarrantyClaim::toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $tabs = [
            'pending' => ['label' => 'Menunggu Ditinjau', 'count' => (int) ($by['pending'] ?? 0)],
            'approved' => ['label' => 'Disetujui', 'count' => (int) ($by['approved'] ?? 0)],
            'rejected' => ['label' => 'Ditolak', 'count' => (int) ($by['rejected'] ?? 0)],
            'all' => ['label' => 'Semua', 'count' => (int) $by->sum()],
        ];

        return view('admin.warranty.index', compact('claims', 'tabs', 'current'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'admin_note' => ['required_if:status,rejected', 'nullable', 'string', 'max:500'],
        ], [
            'status.required' => 'Pilih keputusan: setujui atau tolak.',
            'admin_note.required_if' => 'Alasan penolakan wajib diisi agar pelanggan paham.',
            'admin_note.max' => 'Catatan maksimal 500 karakter.',
        ]);

        $result = DB::transaction(function () use ($id, $data) {
            $claim = WarrantyClaim::lockForUpdate()->findOrFail($id);

            if ($claim->status !== 'pending') {
                return false;
            }

            $claim->update([
                'status' => $data['status'],
                'admin_note' => trim(strip_tags((string) ($data['admin_note'] ?? ''))) ?: null,
            ]);

            return true;
        });

        if (! $result) {
            return back()->with('error', 'Klaim ini sudah diproses sebelumnya.');
        }

        return back()->with('success', 'Keputusan klaim garansi disimpan.');
    }
}