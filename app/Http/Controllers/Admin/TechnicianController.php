<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Admin\Concerns\HandlesAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccountRequest;
use App\Models\TechnicianProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TechnicianController extends Controller
{
    use HandlesAvatar;

    private const ACTIVE_JOB_STATUSES = ['paid', 'on_the_way', 'in_progress'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,active,inactive'],
        ]);

        $term = trim((string) ($filters['q'] ?? ''));
        $status = $filters['status'] ?? 'all';

        $technicians = User::where('role', UserRole::Technician->value)
            ->with('technicianProfile')
            ->withCount([
                'assignedOrders as jobs_active' => fn ($q) => $q->whereIn('status', self::ACTIVE_JOB_STATUSES),
                'assignedOrders as jobs_completed' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->when($term !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
            ))
            ->when($status !== 'all', fn ($q) => $q->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.technicians.index', compact('technicians', 'term', 'status'));
    }

    public function create(): View
    {
        return view('admin.technicians.form', ['account' => null]);
    }

    public function store(AccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $active = $request->boolean('is_active');

        $user = DB::transaction(function () use ($request, $data, $active) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role' => UserRole::Technician,
                'is_active' => $active,
            ]);

            TechnicianProfile::create([
                'user_id' => $user->id,
                'bio' => $data['bio'] ?? null,
                'is_active' => $active,
            ]);

            if ($request->hasFile('avatar')) {
                $user->update(['avatar' => $this->replaceAvatar($request->file('avatar'), null)]);
            }

            return $user;
        });

        return redirect()->route('admin.technicians.index')->with('success', "Teknisi {$user->name} ditambahkan.");
    }

    public function edit(int $id): View
    {
        return view('admin.technicians.form', ['account' => $this->technician($id)]);
    }

    public function update(AccountRequest $request, int $id): RedirectResponse
    {
        $user = $this->technician($id);
        $data = $request->validated();
        $active = $request->boolean('is_active');

        if (! $active && $user->is_active && $this->hasActiveJobs($user)) {
            return back()->withInput()->with('error', 'Teknisi ini masih punya pekerjaan aktif, jadi belum bisa dinonaktifkan.');
        }

        $passwordChanged = ! empty($data['password']);

        DB::transaction(function () use ($request, $user, $data, $active, $passwordChanged) {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'is_active' => $active,
            ]);

            if ($passwordChanged) {
                $user->password = $data['password'];
            }

            if ($request->hasFile('avatar')) {
                $user->avatar = $this->replaceAvatar($request->file('avatar'), $user->avatar);
            }

            $user->save();

            TechnicianProfile::updateOrCreate(
                ['user_id' => $user->id],
                ['bio' => $data['bio'] ?? null, 'is_active' => $active]
            );
        });

        if (! $active || $passwordChanged) {
            $user->tokens()->delete();
        }

        return redirect()->route('admin.technicians.index')->with('success', 'Data teknisi diperbarui.');
    }

    public function toggle(int $id): RedirectResponse
    {
        $user = $this->technician($id);
        $newState = ! $user->is_active;

        if (! $newState && $this->hasActiveJobs($user)) {
            return back()->with('error', 'Teknisi ini masih punya pekerjaan aktif, jadi belum bisa dinonaktifkan.');
        }

        DB::transaction(function () use ($user, $newState) {
            $user->update(['is_active' => $newState]);
            TechnicianProfile::updateOrCreate(['user_id' => $user->id], ['is_active' => $newState]);
        });

        if (! $newState) {
            $user->tokens()->delete();
        }

        return back()->with('success', $newState ? 'Teknisi diaktifkan.' : 'Teknisi dinonaktifkan.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $user = $this->technician($id);

        if ($user->assignedOrders()->exists()) {
            return back()->with('error', 'Teknisi ini punya riwayat pekerjaan sehingga tidak dapat dihapus. Nonaktifkan saja akunnya.');
        }

        $this->deleteAvatar($user->avatar);
        $user->tokens()->delete();
        $user->delete(); // profil teknisi ikut terhapus (cascade)

        return redirect()->route('admin.technicians.index')->with('success', 'Teknisi dihapus.');
    }

    private function technician(int $id): User
    {
        return User::where('role', UserRole::Technician->value)->with('technicianProfile')->findOrFail($id);
    }

    private function hasActiveJobs(User $technician): bool
    {
        return $technician->assignedOrders()->whereIn('status', self::ACTIVE_JOB_STATUSES)->exists();
    }
}