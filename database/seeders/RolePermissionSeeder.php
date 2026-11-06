<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissions = [
            'dashboard.admin.view',
            'events.view',
            'events.create',
            'events.update',
            'events.delete',
            'teams.view_all',
            'teams.manage_status',
            'screening.view_queue',
            'screening.verify_player',
            'screening.unlock_player',
            'payments.view_all',
            'payments.verify_manual',
            'payments.export_report',
            'payments.configure_gateway',
            'users.manage',
            'roles.manage',
            'email_logs.manage',
            'manager.portal.access',
            'manager.team.update_own',
            'manager.payment.pay_own',
            'manager.players.manage_own',
        ];

        foreach ($allPermissions as $permName) {
            Permission::findOrCreate($permName, 'web');
        }

        $superadminRole = Role::findOrCreate('superadmin', 'web');
        $financeRole = Role::findOrCreate('finance', 'web');
        $screeningRole = Role::findOrCreate('screening_officer', 'web');
        $managerRole = Role::findOrCreate('team_manager', 'web');

        $superadminRole->syncPermissions([
            'dashboard.admin.view',
            'events.view',
            'events.create',
            'events.update',
            'events.delete',
            'teams.view_all',
            'teams.manage_status',
            'screening.view_queue',
            'screening.verify_player',
            'screening.unlock_player',
            'payments.view_all',
            'payments.verify_manual',
            'payments.export_report',
            'payments.configure_gateway',
            'users.manage',
            'roles.manage',
            'email_logs.manage',
        ]);

        $financeRole->syncPermissions([
            'dashboard.admin.view',
            'teams.view_all',
            'payments.view_all',
            'payments.verify_manual',
            'payments.export_report',
        ]);

        $screeningRole->syncPermissions([
            'dashboard.admin.view',
            'teams.view_all',
            'screening.view_queue',
            'screening.verify_player',
            'screening.unlock_player',
        ]);

        $managerRole->syncPermissions([
            'manager.portal.access',
            'manager.team.update_own',
            'manager.payment.pay_own',
            'manager.players.manage_own',
        ]);

        // Helper untuk membuat user internal tanpa menimpa kata sandi jika sudah ada
        $seedInternalUser = function (string $email, string $name, string $phone, string $role, string $defaultPassword) {
            $user = User::firstOrNew(['email' => $email]);
            if (! $user->exists) {
                $user->password = Hash::make(env('SEEDER_' . strtoupper($role) . '_PASSWORD', $defaultPassword));
            }
            $user->name = $name;
            $user->phone = $phone;
            $user->is_active = true;
            if (! $user->email_verified_at) {
                $user->email_verified_at = now();
            }
            $user->save();
            $user->syncRoles([$role]);

            return $user;
        };

        // Akun Utama Super Admin Persija Development
        $seedInternalUser(
            email: 'superadmin@persijadevelopment.id',
            name: 'Superadmin Persija Development',
            phone: '081128021928',
            role: 'superadmin',
            defaultPassword: 'Admin12345'
        );

        // Akun Super Admin Standar
        $seedInternalUser(
            email: 'superadmin@ytevent.id',
            name: 'Superadmin Persija Development',
            phone: '08111000111',
            role: 'superadmin',
            defaultPassword: 'Admin12345'
        );

        // Akun Finance
        $seedInternalUser(
            email: 'finance@persijadevelopment.id',
            name: 'Finance Persija Development',
            phone: '081128021929',
            role: 'finance',
            defaultPassword: 'Finance12345'
        );

        $seedInternalUser(
            email: 'finance@ytevent.id',
            name: 'Staf Finance Persija',
            phone: '08111000222',
            role: 'finance',
            defaultPassword: 'Finance12345'
        );

        // Akun Screening Officer
        $seedInternalUser(
            email: 'screening@persijadevelopment.id',
            name: 'Petugas Screening Persija',
            phone: '081128021930',
            role: 'screening_officer',
            defaultPassword: 'Screening12345'
        );
    }
}

