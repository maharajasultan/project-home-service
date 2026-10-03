<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Support\CsvExport;
use App\Support\ReportPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryReportController extends Controller
{
    public function index(Request $request): View
    {
        $period = ReportPeriod::resolve($request);
        $f = $this->filters($request);

        $recap = [];
        $variants = null;
        $log = null;

        if ($f['tab'] === 'log') {
            $log = $this->logQuery($period, $f)->paginate(25)->withQueryString();
        } else {
            $variants = $this->variantQuery($period, $f)->paginate(25)->withQueryString();
            $recap = $this->recap($variants->getCollection(), $period);
        }

        return view('admin.reports.inventory', [
            'period' => $period,
            'f' => $f,
            'summary' => $this->summary($period, $f['product']),
            'products' => Product::where('type', 'sparepart')->orderBy('name')->get(['id', 'name']),
            'variants' => $variants,
            'recap' => $recap,
            'log' => $log,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $period = ReportPeriod::resolve($request);
        $f = $this->filters($request);

        if ($f['tab'] === 'log') {
            $query = $this->logQuery($period, $f)->reorder()->orderBy('id');

            $rows = (function () use ($query) {
                foreach ($query->lazy(500) as $m) {
                    yield [
                        $m->created_at->format('d-m-Y H:i'),
                        $m->variant?->product?->name ?? '-',
                        $m->variant?->label() ?? '-',
                        $m->type === 'in' ? 'Masuk' : 'Keluar',
                        $m->qty,
                        $m->type === 'in' ? 'Barang masuk' : ($m->order_id ? 'Terjual' : 'Koreksi'),
                        $m->order?->order_code ?? '',
                        $m->note ?? '',
                        $m->creator?->name ?? 'Sistem',
                    ];
                }
            })();

            return CsvExport::download(
                "riwayat-stok_{$period->fromDate()}_{$period->toDate()}.csv",
                ['Waktu', 'Produk', 'Varian', 'Jenis', 'Jumlah', 'Keterangan Jenis', 'Kode Pesanan', 'Catatan', 'Dicatat Oleh'],
                $rows
            );
        }

        $recap = $this->recap($this->variantQuery($period, $f)->get(), $period);

        $rows = array_map(fn (array $r) => [
            $r['name'], $r['label'], $r['opening'], $r['in'], $r['sold'], $r['adjust'], $r['closing'], $r['current'],
        ], $recap);

        return CsvExport::download(
            "rekap-inventory_{$period->fromDate()}_{$period->toDate()}.csv",
            ['Produk', 'Varian', 'Stok Awal (buku)', 'Masuk', 'Terjual', 'Koreksi Keluar', 'Stok Akhir (buku)', 'Stok Sistem Saat Ini'],
            $rows
        );
    }

    // ------------------------------------------------------------------

    private function filters(Request $request): array
    {
        $data = $request->validate([
            'product' => ['nullable', 'integer', 'exists:products,id'],
            'tab' => ['nullable', 'in:recap,log'],
            'move' => ['nullable', 'in:all,in,out'],
            'activity' => ['nullable', 'in:all,moved'],
        ]);

        return [
            'product' => isset($data['product']) ? (int) $data['product'] : null,
            'tab' => $data['tab'] ?? 'recap',
            'move' => $data['move'] ?? 'all',
            'activity' => $data['activity'] ?? 'all',
        ];
    }

    private function sparepartVariants(?int $productId): Builder
    {
        return ProductVariant::query()->whereHas(
            'product',
            fn ($q) => $q->where('type', 'sparepart')->when($productId, fn ($w) => $w->whereKey($productId))
        );
    }

    private function variantQuery(ReportPeriod $period, array $f): Builder
    {
        return $this->sparepartVariants($f['product'])
            ->with(['product:id,name', 'iphoneModel:id,name'])
            ->when($f['activity'] === 'moved', fn ($q) => $q->whereIn(
                'id',
                StockMovement::select('product_variant_id')->whereBetween('created_at', [$period->from, $period->to])
            ))
            ->orderBy('product_id')
            ->orderBy('iphone_model_id')
            ->orderByRaw("FIELD(grade, 'standar', 'premium', 'original')");
    }

    private function logQuery(ReportPeriod $period, array $f): Builder
    {
        return StockMovement::query()
            ->with(['variant.product:id,name', 'variant.iphoneModel:id,name', 'order:id,order_code', 'creator:id,name'])
            ->whereBetween('created_at', [$period->from, $period->to])
            ->when($f['move'] !== 'all', fn ($q) => $q->where('type', $f['move']))
            ->when($f['product'], fn ($q, $id) => $q->whereHas('variant', fn ($v) => $v->where('product_id', $id)))
            ->orderByDesc('id');
    }

    private function summary(ReportPeriod $period, ?int $productId): array
    {
        $row = StockMovement::toBase()
            ->join('product_variants', 'product_variants.id', '=', 'stock_movements.product_variant_id')
            ->whereBetween('stock_movements.created_at', [$period->from, $period->to])
            ->when($productId, fn ($q, $id) => $q->where('product_variants.product_id', $id))
            ->selectRaw("
                COALESCE(SUM(CASE WHEN stock_movements.type = 'in' THEN stock_movements.qty END), 0) AS qty_in,
                COALESCE(SUM(CASE WHEN stock_movements.type = 'out' AND stock_movements.order_id IS NOT NULL THEN stock_movements.qty END), 0) AS qty_sold,
                COALESCE(SUM(CASE WHEN stock_movements.type = 'out' AND stock_movements.order_id IS NULL THEN stock_movements.qty END), 0) AS qty_adjust
            ")
            ->first();

        return [
            'in' => (int) $row->qty_in,
            'sold' => (int) $row->qty_sold,
            'adjust' => (int) $row->qty_adjust,
            'stock_now' => (int) $this->sparepartVariants($productId)->sum('stock'),
            'low_stock' => $this->sparepartVariants($productId)->where('is_active', true)->where('stock', '<=', 3)->count(),
        ];
    }

    /** Rekap per varian: stok awal (buku) + masuk - keluar = stok akhir (buku). */
    private function recap(Collection $variants, ReportPeriod $period): array
    {
        $ids = $variants->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        $moved = StockMovement::toBase()
            ->whereIn('product_variant_id', $ids)
            ->whereBetween('created_at', [$period->from, $period->to])
            ->selectRaw("
                product_variant_id,
                SUM(CASE WHEN type = 'in' THEN qty ELSE 0 END) AS qty_in,
                SUM(CASE WHEN type = 'out' AND order_id IS NOT NULL THEN qty ELSE 0 END) AS qty_sold,
                SUM(CASE WHEN type = 'out' AND order_id IS NULL THEN qty ELSE 0 END) AS qty_adjust
            ")
            ->groupBy('product_variant_id')
            ->get()
            ->keyBy('product_variant_id');

        $before = StockMovement::toBase()
            ->whereIn('product_variant_id', $ids)
            ->where('created_at', '<', $period->from)
            ->selectRaw("product_variant_id, SUM(CASE WHEN type = 'in' THEN qty ELSE -qty END) AS balance")
            ->groupBy('product_variant_id')
            ->pluck('balance', 'product_variant_id');

        return $variants->map(function (ProductVariant $v) use ($moved, $before) {
            $m = $moved->get($v->id);
            $in = (int) ($m->qty_in ?? 0);
            $sold = (int) ($m->qty_sold ?? 0);
            $adjust = (int) ($m->qty_adjust ?? 0);
            $opening = (int) ($before[$v->id] ?? 0);

            return [
                'name' => $v->product->name,
                'label' => $v->label(),
                'active' => $v->is_active,
                'opening' => $opening,
                'in' => $in,
                'sold' => $sold,
                'adjust' => $adjust,
                'closing' => $opening + $in - $sold - $adjust,
                'current' => (int) $v->stock,
            ];
        })->values()->all();
    }
}