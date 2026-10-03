<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductType;
use App\Http\Controllers\Admin\Concerns\HandlesMedia;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    use HandlesMedia;

    private const MAX_IMAGES = 6;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(ProductType::class)],
            'status' => ['nullable', 'in:all,active,inactive'],
        ]);

        $term = trim((string) ($filters['q'] ?? ''));
        $type = $filters['type'] ?? '';
        $status = $filters['status'] ?? 'all';

        $products = Product::query()
            ->withCount([
                'variants',
                'variants as low_stock' => fn ($q) => $q->where('is_active', true)->where('stock', '<=', 3),
            ])
            ->withSum('variants as total_stock', 'stock')
            ->withMin('variants as min_price', 'price')
            ->withMax('variants as max_price', 'price')
            ->when($term !== '', fn ($q) => $q->where('name', 'like', "%{$term}%"))
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($status !== 'all', fn ($q) => $q->where('is_active', $status === 'active'))
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.products.index', compact('products', 'term', 'type', 'status'));
    }

    public function create(): View
    {
        return view('admin.products.form', ['product' => null]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $paths = [];
        foreach ($request->file('images', []) as $file) {
            $paths[] = $this->storeFile($file, 'products');
        }

        $product = Product::create([
            'name' => trim($data['name']),
            'slug' => $this->uniqueSlug($data['name']),
            'type' => $data['type'],
            'description' => trim(strip_tags((string) ($data['description'] ?? ''))) ?: null,
            'images' => $paths ?: null,
            'base_fee' => (int) ($data['base_fee'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.products.variants', $product->id)
            ->with('success', 'Produk dibuat. Sekarang tambahkan varian dan harganya.');
    }

    public function edit(int $id): View
    {
        return view('admin.products.form', ['product' => Product::findOrFail($id)]);
    }

    public function update(ProductRequest $request, int $id): RedirectResponse
    {
        $product = Product::findOrFail($id);
        $data = $request->validated();

        $existing = $product->images ?? [];
        $remove = array_values(array_intersect($existing, $data['remove_images'] ?? []));
        $kept = array_values(array_diff($existing, $remove));
        $uploads = $request->file('images', []);

        if (count($kept) + count($uploads) > self::MAX_IMAGES) {
            return back()->withInput()->with('error', 'Maksimal '.self::MAX_IMAGES.' foto per produk. Hapus beberapa foto lama dulu.');
        }

        $stored = [];
        foreach ($uploads as $file) {
            $stored[] = $this->storeFile($file, 'products');
        }

        $images = array_merge($kept, $stored);

        $product->update([
            'name' => trim($data['name']),
            'description' => trim(strip_tags((string) ($data['description'] ?? ''))) ?: null,
            'base_fee' => (int) ($data['base_fee'] ?? 0),
            'is_active' => $request->boolean('is_active'),
            'images' => $images ?: null,
        ]);

        foreach ($remove as $path) {
            $this->deleteFile($path);
        }

        return redirect()->route('admin.products.index')->with('success', 'Produk diperbarui.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $product = Product::findOrFail($id);
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', $product->is_active
            ? 'Produk ditampilkan di aplikasi.'
            : 'Produk disembunyikan dari aplikasi.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $product = Product::findOrFail($id);

        if (OrderItem::where('product_id', $product->id)->exists()) {
            return back()->with('error', 'Produk ini sudah pernah dipesan sehingga tidak dapat dihapus. Nonaktifkan saja agar tidak tampil di aplikasi.');
        }

        $images = $product->images ?? [];

        DB::transaction(function () use ($product) {
            Review::where('target_type', 'product')->where('target_id', $product->id)->delete();
            $product->delete(); // varian, keranjang, dan riwayat stok ikut terhapus (cascade)
        });

        foreach ($images as $path) {
            $this->deleteFile($path);
        }

        return redirect()->route('admin.products.index')->with('success', 'Produk dihapus.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'produk';
        $slug = $base;
        $i = 2;

        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}