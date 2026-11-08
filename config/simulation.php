<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sakelar Master Modul Simulasi (Take-Out Ready)
    |--------------------------------------------------------------------------
    | KEAMANAN: Default MATI (false). Modul ini mengizinkan pindah akun tanpa
    | login (switch persona) sehingga HANYA boleh dinyalakan di lingkungan
    | lokal/staging dengan SIMULATION_MODULE_ENABLED=true.
    |
    | Di APP_ENV=production modul DIPAKSA mati, apa pun nilai .env-nya.
    | Selain itu dapat dihidup-matikan secara dinamis melalui Pengaturan
    | Global Superadmin (system_settings key: 'simulation_mode_enabled').
    */
    'master_enabled' => env('APP_ENV') !== 'production'
        && (bool) env('SIMULATION_MODULE_ENABLED', false),
];
