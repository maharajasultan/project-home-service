<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesMedia;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BannerController extends Controller
{
    use HandlesMedia;

    public function index(): View
    {
        return view('admin.banners.index', [
            'banners' => Banner::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.banners.form', ['banner' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules(true), $this->messages());

        $path = $this->storeFile($request->file('image'), 'banners');

        Banner::create([
            'title' => trim(strip_tags($data['title'])),
            'image' => $path,
            'sort_order' => $data['sort_order'] ?? ((int) Banner::max('sort_order') + 1),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.banners.index')->with('success', 'Banner ditambahkan.');
    }

    public function edit(int $id): View
    {
        return view('admin.banners.form', ['banner' => Banner::findOrFail($id)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $banner = Banner::findOrFail($id);
        $data = $request->validate($this->rules(false), $this->messages());

        $oldImage = $banner->image;
        $attributes = [
            'title' => trim(strip_tags($data['title'])),
            'sort_order' => $data['sort_order'] ?? $banner->sort_order,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->hasFile('image')) {
            $attributes['image'] = $this->storeFile($request->file('image'), 'banners');
        }

        $banner->update($attributes);

        if ($request->hasFile('image')) {
            $this->deleteFile($oldImage);
        }

        return redirect()->route('admin.banners.index')->with('success', 'Banner diperbarui.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $banner = Banner::findOrFail($id);
        $banner->update(['is_active' => ! $banner->is_active]);

        return back()->with('success', $banner->is_active ? 'Banner ditampilkan.' : 'Banner disembunyikan.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $banner = Banner::findOrFail($id);
        $image = $banner->image;
        $banner->delete();
        $this->deleteFile($image);

        return back()->with('success', 'Banner dihapus.');
    }

    private function rules(bool $creating): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'image' => [
                $creating ? 'required' : 'nullable',
                'file', 'mimes:jpg,jpeg,png,webp', 'max:3072',
                'dimensions:min_width=600,min_height=200',
            ],
            'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'title.required' => 'Judul banner wajib diisi.',
            'image.required' => 'Pilih gambar banner.',
            'image.mimes' => 'Gambar harus berformat JPG, PNG, atau WEBP.',
            'image.max' => 'Ukuran gambar maksimal 3 MB.',
            'image.dimensions' => 'Gambar terlalu kecil. Minimal 600x200 piksel (disarankan 1200x500).',
            'image.uploaded' => 'Gambar gagal diunggah. Ukuran terlalu besar.',
            'sort_order.integer' => 'Urutan harus berupa angka.',
        ];
    }
}