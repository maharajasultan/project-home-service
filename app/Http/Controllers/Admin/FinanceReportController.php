<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceTransaction;
use App\Models\Order;
use App\Support\CsvExport;
use App\Support\ReportPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceReportController extends Controller
{
    public function index(Request $request): View
    {
        $period = ReportPeriod::resolve($request);
        [$type, $category] = $this->filters($request);

        $range = [$period->fromDate(), $period->toDate()];

        // Ringkasan selalu untuk seluruh periode (tidak terpengaruh filter jenis/kategori).
        $totals = FinanceTransaction::toBase()
            ->whereBetween('transaction_date', $range)
            ->selectRaw('type, sum(amount) as total, count(*) as cnt')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $breakdown = FinanceTransaction::toBase()
            ->whereBetween('transaction_date', $range)
            ->selectRaw('type, category, sum(amount) as total, count(*) as cnt')
            ->groupBy('type', 'category')
            ->orderByDesc('total')
            ->get();

        $chartRows = FinanceTransaction::toBase()
            ->whereBetween('transaction_date', $range)
            ->selectRaw('transaction_date as d, type, sum(amount) as total')
            ->groupBy('transaction_date', 'type')
            ->get();

        $summary = [
            'in' => (int) ($totals['in']->total ?? 0),
            'out' => (int) ($totals['out']->total ?? 0),
            'count' => (int) (($totals['in']->cnt ?? 0) + ($totals['out']->cnt ?? 0)),
        ];
        $summary['net'] = $summary['in'] - $summary['out'];

        $transactions = $this->base($period, $type, $category)
            ->with(['order:id,order_code', 'creator:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.reports.finance', [
            'period' => $period,
            'type' => $type,
            'category' => $category,
            'summary' => $summary,
            'breakdown' => $breakdown,
            'chart' => $this->chart($period, $chartRows),
            'transactions' => $transactions,
        ]);
    }

    /** Catat pengeluaran manual (uang keluar). */
    public function storeExpense(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'transaction_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'category' => ['required', Rule::in(array_keys(FinanceTransaction::EXPENSE_CATEGORIES))],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'description' => ['required', 'string', 'max:190'],
            'order_code' => ['nullable', 'string', 'max:30'],
        ], [
            'transaction_date.required' => 'Tanggal wajib diisi.',
            'transaction_date.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'category.required' => 'Pilih kategori pengeluaran.',
            'amount.required' => 'Nominal wajib diisi.',
            'amount.integer' => 'Nominal harus berupa angka tanpa titik atau koma.',
            'amount.min' => 'Nominal minimal Rp1.',
            'description.required' => 'Keterangan wajib diisi.',
            'description.max' => 'Keterangan maksimal 190 karakter.',
        ]);

        $orderId = null;
        $code = strtoupper(trim((string) ($data['order_code'] ?? '')));

        if ($code !== '') {
            if ($data['category'] !== 'refund') {
                return back()->withInput()->withErrors(['order_code' => 'Kode pesanan hanya dipakai untuk kategori Refund Pelanggan.']);
            }

            $orderId = Order::where('order_code', $code)->value('id');

            if (! $orderId) {
                return back()->withInput()->withErrors(['order_code' => "Kode pesanan {$code} tidak ditemukan."]);
            }
        }

        FinanceTransaction::create([
            'order_id' => $orderId,
            'type' => 'out',
            'category' => $data['category'],
            'amount' => (int) $data['amount'],
            'description' => trim(strip_tags($data['description'])),
            'transaction_date' => $data['transaction_date'],
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Pengeluaran dicatat.');
    }

    public function destroyExpense(int $id): RedirectResponse
    {
        $transaction = FinanceTransaction::findOrFail($id);

        if (! $transaction->isManual()) {
            return back()->with('error', 'Transaksi otomatis (penjualan dan pembelian stok) tidak dapat dihapus dari laporan.');
        }

        $transaction->delete();

        return back()->with('success', 'Pengeluaran dihapus.');
    }

    public function export(Request $request): StreamedResponse
    {
        $period = ReportPeriod::resolve($request);
        [$type, $category] = $this->filters($request);

        $query = $this->base($period, $type, $category)
            ->with('order:id,order_code')
            ->orderBy('transaction_date')
            ->orderBy('id');

        $rows = (function () use ($query) {
            $in = 0;
            $out = 0;

            foreach ($query->lazy(500) as $t) {
                if ($t->type === 'in') {
                    $in += $t->amount;
                } else {
                    $out += $t->amount;
                }

                yield [
                    $t->transaction_date->format('d-m-Y'),
                    $t->type === 'in' ? 'Uang Masuk' : 'Uang Keluar',
                    FinanceTransaction::CATEGORY_LABELS[$t->category] ?? '-',
                    $t->description,
                    $t->order?->order_code ?? '',
                    $t->type === 'in' ? $t->amount : '',
                    $t->type === 'out' ? $t->amount : '',
                ];
            }

            yield [];
            yield ['', '', '', 'TOTAL', '', $in, $out];
            yield ['', '', '', 'ARUS KAS BERSIH', '', $in - $out, ''];
        })();

        return CsvExport::download(
            "laporan-keuangan_{$period->fromDate()}_{$period->toDate()}.csv",
            ['Tanggal', 'Jenis', 'Kategori', 'Keterangan', 'Kode Pesanan', 'Uang Masuk (Rp)', 'Uang Keluar (Rp)'],
            $rows
        );
    }

    // ------------------------------------------------------------------

    /** @return array{0: string, 1: string} [type, category] */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:all,in,out'],
            'category' => ['nullable', Rule::in(array_keys(FinanceTransaction::CATEGORY_LABELS))],
        ]);

        return [$data['type'] ?? 'all', $data['category'] ?? ''];
    }

    private function base(ReportPeriod $period, string $type, string $category): Builder
    {
        return FinanceTransaction::query()
            ->whereBetween('transaction_date', [$period->fromDate(), $period->toDate()])
            ->when($type !== 'all', fn ($q) => $q->where('type', $type))
            ->when($category !== '', fn ($q) => $q->where('category', $category));
    }

    /** Harian jika rentang <= 62 hari, selain itu bulanan. */
    private function chart(ReportPeriod $period, Collection $rows): array
    {
        $monthly = $period->days() > 62;
        $buckets = [];
        $cursor = $monthly ? $period->from->copy()->startOfMonth() : $period->from->copy()->startOfDay();

        while ($cursor->lte($period->to)) {
            $key = $monthly ? $cursor->format('Y-m') : $cursor->toDateString();
            $buckets[$key] = ['label' => $monthly ? $cursor->format('m/Y') : $cursor->format('d/m'), 'in' => 0, 'out' => 0];
            $monthly ? $cursor->addMonthNoOverflow() : $cursor->addDay();
        }

        foreach ($rows as $row) {
            $key = $monthly ? substr((string) $row->d, 0, 7) : substr((string) $row->d, 0, 10);

            if (isset($buckets[$key])) {
                $buckets[$key][$row->type] += (int) $row->total;
            }
        }

        return [
            'labels' => array_column($buckets, 'label'),
            'in' => array_column($buckets, 'in'),
            'out' => array_column($buckets, 'out'),
            'monthly' => $monthly,
        ];
    }
}