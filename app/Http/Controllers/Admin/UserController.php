<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Admin\Concerns\HandlesAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    use HandlesAvatar;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,active,inactive'],
        ]);

        $term = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? 'all';

        $users = User::where('role', UserRole::User->value)
            ->withCount('orders')
            ->when($term !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
            ))
            ->when($status !== 'all', fn ($q) => $q->where('is_active', $status === 'active'))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'term', 'status'));
    }

    public function create(): View
    {
        return view('admin.users.form', ['account' => null]);
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'role' => UserRole::User,
            'is_active' => $request->boolean('is_active'),
        ]);

        if ($request->hasFile('avatar')) {
            $user->update(['avatar' => $this->replaceAvatar($request->file('avatar'), null)]);
        }

        return redirect()->route('admin.users.index')->with('success', "Pelanggan {$user->name} ditambahkan.");
    }

    public function edit(int $id): View
    {
        return view('admin.users.form', ['account' => $this->customer($id)]);
    }

    public function update(AccountRequest $request, int $id): RedirectResponse
    {
        $user = $this->customer($id);
        $data = $request->validated();
        $active = $request->boolean('is_active');
        $passwordChanged = ! empty($data['password']);

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $active,
        ]);

        if ($passwordChanged) {
            $user->password = $data['password'];
        }

        if ($request->hasFile('avatar')) {
            $user->avatar = $this->replaceAvatar($request->file('avatar'), $user->avatar);
        }

        $user->save();

        if (! $active || $passwordChanged) {
            $user->tokens()->delete(); // paksa login ulang di aplikasi
        }

        return redirect()->route('admin.users.index')->with('success', 'Data pelanggan diperbarui.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $user = $this->customer($id);
        $user->update(['is_active' => ! $user->is_active]);

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return back()->with('success', $user->is_active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan dan dikeluarkan dari aplikasi.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = $this->customer($id);

        if ($user->orders()->exists()) {
            return back()->with('error', 'Pelanggan ini punya riwayat pesanan sehingga tidak dapat dihapus. Nonaktifkan saja akunnya.');
        }

        $this->deleteAvatar($user->avatar);
        $user->tokens()->delete();
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Pelanggan dihapus.');
    }

    private function customer(int $id): User
    {
        return User::where('role', UserRole::User->value)->findOrFail($id);
    }
}