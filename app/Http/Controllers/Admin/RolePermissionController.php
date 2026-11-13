<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRolePermissionsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function index(): Response
    {
        $roles = Role::whereIn('name', ['superadmin', 'finance', 'screening_officer'])
            ->with('permissions')
            ->get()
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'label' => match ($r->name) {
                    'superadmin' => 'Super Administrator',
                    'finance' => 'Finance & Pembayaran',
                    'screening_officer' => 'Screening Officer',
                    default => ucfirst($r->name),
                },
                'permissions' => $r->permissions->pluck('name')->values(),
            ]);

        $permissions = Permission::all(['id', 'name'])->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'group' => explode('.', $p->name)[0] ?? 'general',
            'label' => match ($p->name) {
                'dashboard.admin.view' => 'Akses Dashboard Panitia',
                'events.view' => 'Melihat Daftar Kompetisi',
                'events.create' => 'Membuat Kompetisi Baru',
                'events.update' => 'Mengedit Regulasi & Kuota Event',
                'events.delete' => 'Menghapus Kompetisi',
                'teams.view_all' => 'Melihat Seluruh Tim Terdaftar',
                'teams.manage' => 'Kelola Status Tim',
                'payments.view_all' => 'Melihat Riwayat Transaksi',
                'payments.verify_manual' => 'Verifikasi Bukti Transfer Manual',
                'players.view_all' => 'Melihat Seluruh Data Pemain',
                'players.screen' => 'Screening & Verifikasi Berkas Pemain',
                default => $p->name,
            },
        ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'allPermissions' => $permissions,
        ]);
    }

    public function update(UpdateRolePermissionsRequest $request, Role $role): RedirectResponse
    {
        if ($role->name === 'superadmin') {
            return back()->with('error', 'Hak akses Super Administrator bersifat mutlak dan tidak dapat dibatasi.');
        }

        $validated = $request->validated();

        $role->syncPermissions($validated['permissions']);

        return back()->with('success', "Hak akses izin (permissions) untuk peran {$role->name} berhasil diperbarui.");
    }
}

