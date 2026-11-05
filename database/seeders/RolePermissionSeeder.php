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

        // Akun Utama Super Admin Persija Development
        $superadminPersija = User::updateOrCreate(
            ['email' => 'superadmin@persijadevelopment.id'],
            [
                'name' => 'Superadmin Persija Development',
                'phone' => '081128021928',
                'password' => Hash::make('Admin12345'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $superadminPersija->syncRoles(['superadmin']);

        // Akun Super Admin Standar
        $superadmin = User::updateOrCreate(
            ['email' => 'superadmin@ytevent.id'],
            [
                'name' => 'Superadmin Persija Development',
                'phone' => '08111000111',
                'password' => Hash::make('Admin12345'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $superadmin->syncRoles(['superadmin']);

        // Akun Finance
        $financePersija = User::updateOrCreate(
            ['email' => 'finance@persijadevelopment.id'],
            [
                'name' => 'Finance Persija Development',
                'phone' => '081128021929',
                'password' => Hash::make('Finance12345'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $financePersija->syncRoles(['finance']);

        $finance = User::updateOrCreate(
            ['email' => 'finance@ytevent.id'],
            [
                'name' => 'Staf Finance Persija',
                'phone' => '08111000222',
                'password' => Hash::make('Finance12345'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $finance->syncRoles(['finance']);

        // Akun Screening Officer
        $screening = User::updateOrCreate(
            ['email' => 'screening@persijadevelopment.id'],
            [
                'name' => 'Petugas Screening Persija',
                'phone' => '081128021930',
                'password' => Hash::make('Screening12345'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $screening->syncRoles(['screening_officer']);
    }
}

