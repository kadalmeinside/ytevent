<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $roleFilter = $request->query('role');

        $users = User::query()
            ->with(['roles', 'teams:id,user_id,name'])
            ->when($search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('teams', fn ($tq) => $tq->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($roleFilter, function ($q, $role) {
                $q->whereHas('roles', function ($sub) use ($role) {
                    $sub->where('name', $role);
                });
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'is_active' => (bool) $u->is_active,
                'created_at' => $u->created_at?->format('d M Y, H:i'),
                'roles' => $u->getRoleNames()->values(),
                'role' => $u->getRoleNames()->first() ?? '-',
                'teams' => $u->teams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
            ]);

        $availableRoles = Role::query()
            ->get(['id', 'name'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'label' => match ($r->name) {
                    'superadmin' => 'Super Administrator (Full Access)',
                    'finance' => 'Finance & Verifikator Pembayaran',
                    'screening_officer' => 'Screening Officer (Verifikator Berkas Pemain)',
                    'team_manager' => 'Manajer Tim (Peserta Kompetisi)',
                    default => ucfirst($r->name),
                },
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $roleFilter,
            ],
            'roles' => $availableRoles,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => $data['phone'] ? trim($data['phone']) : null,
            'password' => Hash::make($data['password']),
            'is_active' => (bool) $data['is_active'],
            'email_verified_at' => now(),
        ]);

        $user->assignRole($data['role']);

        return redirect()->route('admin.users.index')
            ->with('success', "Pengguna panitia {$user->name} dengan peran {$data['role']} berhasil ditambahkan.");
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $updatePayload = [
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => !empty($data['phone']) ? trim($data['phone']) : null,
            'is_active' => (bool) $data['is_active'],
        ];

        if (!empty($data['password'])) {
            $updatePayload['password'] = Hash::make($data['password']);
        }

        $user->update($updatePayload);
        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')
            ->with('success', "Data pengguna {$user->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $userName = $user->name;
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', "Akun pengguna {$userName} berhasil dihapus dari sistem.");
    }
}

