<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductType;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\IphoneModel;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductVariantController extends Controller
{
    /** Stok "tak terbatas" untuk jasa (Matot, Unlock IMEI). */
    private const SERVICE_STOCK = 999;

    public function __construct(private readonly StockService $stock)
    {
    }

    public function index(int $id): View
    {
        $product = Product::findOrFail($id);

        $variants = ProductVariant::query()
            ->select('product_variants.*')
            ->leftJoin('iphone_models', 'iphone_models.id', '=', 'product_variants.iphone_model_id')
            ->where('product_variants.product_id', $product->id)
            ->orderBy('iphone_models.sort_order')
            ->orderByRaw("FIELD(product_variants.grade, 'standar', 'premium', 'original')")
            ->orderBy('product_variants.duration_months')
            ->with('iphoneModel')
            ->get();

        $models = IphoneModel::active()->get();

        $movements = StockMovement::with('variant.iphoneModel')
            ->whereHas('variant', fn ($q) => $q->where('product_id', $product->id))
            ->latest('id')
            ->limit(10)
            ->get();

        return view('admin.products.variants', compact('product', 'variants', 'models', 'movements'));
    }

    /** Buat banyak varian sekaligus: sparepart (iPhone x grade) atau Matot (iPhone). */
    public function generate(Request $request, int $id): RedirectResponse
    {
        $product = Product::findOrFail($id);
        $isSparepart = $product->type === ProductType::Sparepart;

        if (! $isSparepart && $product->type !== ProductType::Matot) {
            return back()->with('error', 'Produk Unlock IMEI memakai form tambah durasi.');
        }

        $data = $request->validate([
            'models' => ['required', 'array', 'min:1'],
            'models.*' => ['integer', 'exists:iphone_models,id'],
            'grades' => [$isSparepart ? 'required' : 'nullable', 'array', 'min:1'],
            'grades.*' => ['in:'.implode(',', ProductVariant::GRADES)],
            'price' => [$isSparepart ? 'required' : 'nullable', 'integer', 'min:0', 'max:100000000'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ], [
            'models.required' => 'Pilih minimal satu tipe iPhone.',
            'grades.required' => 'Pilih minimal satu grade.',
            'price.required' => 'Harga wajib diisi.',
            'price.integer' => 'Harga harus berupa angka.',
        ]);

        $grades = $isSparepart ? array_unique($data['grades']) : [null];
        $price = $isSparepart ? (int) $data['price'] : 0;
        $stock = $isSparepart ? (int) ($data['stock'] ?? 0) : self::SERVICE_STOCK;
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($product, $data, $grades, $price, $stock, $request, &$created, &$skipped) {
            foreach (array_unique($data['models']) as $modelId) {
                foreach ($grades as $grade) {
                    if ($this->exists($product->id, (int) $modelId, $grade, null)) {
                        $skipped++;
                        continue;
                    }

                    $this->makeVariant($product, (int) $modelId, $grade, null, $price, $stock, (int) $request->user()->id);
                    $created++;
                }
            }
        });

        $message = "{$created} varian dibuat.".($skipped ? " {$skipped} sudah ada dan dilewati." : '');

        return back()->with($created > 0 ? 'success' : 'error', $created > 0 ? $message : 'Semua kombinasi yang dipilih sudah ada.');
    }

    /** Tambah pilihan durasi Unlock IMEI. */
    public function storeDuration(Request $request, int $id): RedirectResponse
    {
        $product = Product::findOrFail($id);

        if ($product->type !== ProductType::UnlockImei) {
            return back()->with('error', 'Durasi hanya untuk produk Unlock IMEI.');
        }

        $data = $request->validate([
            'duration_months' => ['required', 'integer', 'between:1,36'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
        ], [
            'duration_months.required' => 'Durasi (bulan) wajib diisi.',
            'duration_months.between' => 'Durasi harus antara 1 sampai 36 bulan.',
            'price.required' => 'Harga wajib diisi.',
        ]);

        if ($this->exists($product->id, null, null, (int) $data['duration_months'])) {
            return back()->withInput()->with('error', "Durasi {$data['duration_months']} bulan sudah ada.");
        }

        $this->makeVariant($product, null, null, (int) $data['duration_months'], (int) $data['price'], self::SERVICE_STOCK, (int) $request->user()->id);

        return back()->with('success', 'Pilihan durasi ditambahkan.');
    }

    /** Simpan harga dan status aktif semua baris sekaligus. */
    public function updatePrices(Request $request, int $id): RedirectResponse
    {
        $product = Product::findOrFail($id);

        $data = $request->validate([
            'variants' => ['required', 'array'],
            'variants.*.price' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'variants.*.is_active' => ['nullable', 'boolean'],
        ], [
            'variants.*.price.integer' => 'Harga harus berupa angka.',
            'variants.*.price.min' => 'Harga tidak boleh negatif.',
        ]);

        $isMatot = $product->type === ProductType::Matot;
        $changed = 0;

        DB::transaction(function () use ($product, $data, $isMatot, &$changed) {
            $variants = ProductVariant::where('product_id', $product->id)
                ->whereIn('id', array_keys($data['variants']))
                ->lockForUpdate()
                ->get();

            foreach ($variants as $variant) {
                $row = $data['variants'][$variant->id];

                if (! $isMatot && isset($row['price'])) {
                    $variant->price = (int) $row['price'];
                }

                if (array_key_exists('is_active', $row)) {
                    $variant->is_active = (bool) $row['is_active'];
                }

                if ($variant->isDirty()) {
                    $variant->save();
                    $changed++;
                }
            }
        });

        return back()->with('success', $changed ? "{$changed} varian diperbarui." : 'Tidak ada perubahan.');
    }

    /** Barang masuk atau koreksi stok untuk satu varian sparepart. */
    public function adjustStock(Request $request, int $id, int $variantId): RedirectResponse
    {
        $variant = ProductVariant::where('product_id', $id)->findOrFail($variantId);

        $data = $request->validate([
            'type' => ['required', 'in:in,out'],
            'qty' => ['required', 'integer', 'min:1', 'max:10000'],
            'note' => [$request->input('type') === 'out' ? 'required' : 'nullable', 'string', 'max:150'],
            'cost' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ], [
            'type.required' => 'Pilih jenis perubahan stok.',
            'qty.required' => 'Jumlah wajib diisi.',
            'qty.min' => 'Jumlah minimal 1.',
            'note.required' => 'Alasan koreksi wajib diisi (contoh: barang rusak).',
            'cost.integer' => 'Total biaya harus berupa angka.',
        ]);

        try {
            $updated = $this->stock->adjust(
                $variant->id,
                $data['type'],
                (int) $data['qty'],
                $data['note'] ?? null,
                (int) $request->user()->id,
                isset($data['cost']) ? (int) $data['cost'] : null,
            );
        } catch (BusinessException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Stok diperbarui. Stok sekarang: {$updated->stock}.");
    }

    public function destroy(int $id, int $variantId): RedirectResponse
    {
        $variant = ProductVariant::where('product_id', $id)->findOrFail($variantId);

        $used = $variant->orderItems()->exists()
            || $variant->stockMovements()->where('type', 'out')->exists();

        if ($used) {
            return back()->with('error', 'Varian ini sudah pernah terjual atau dikeluarkan dari stok, jadi tidak dapat dihapus. Nonaktifkan saja.');
        }

        $variant->delete();

        return back()->with('success', 'Varian dihapus.');
    }

    // ------------------------------------------------------------------

    private function makeVariant(Product $product, ?int $modelId, ?string $grade, ?int $months, int $price, int $stock, int $adminId): void
    {
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'iphone_model_id' => $modelId,
            'grade' => $grade,
            'duration_months' => $months,
            'price' => $price,
            'stock' => $stock,
            'is_active' => true,
        ]);

        if ($product->type === ProductType::Sparepart && $stock > 0) {
            StockMovement::create([
                'product_variant_id' => $variant->id,
                'type' => 'in',
                'qty' => $stock,
                'note' => 'Stok awal',
                'created_by' => $adminId,
            ]);
        }
    }

    private function exists(int $productId, ?int $modelId, ?string $grade, ?int $months): bool
    {
        $query = ProductVariant::where('product_id', $productId);

        foreach (['iphone_model_id' => $modelId, 'grade' => $grade, 'duration_months' => $months] as $column => $value) {
            $value === null ? $query->whereNull($column) : $query->where($column, $value);
        }

        return $query->exists();
    }
}