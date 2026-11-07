<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sakelar Master Modul Simulasi (Take-Out Ready)
    |--------------------------------------------------------------------------
    | Jika disetel ke false pada .env (SIMULATION_MODULE_ENABLED=false),
    | seluruh rute, tombol melayang, dan aksi simulasi dimatikan sepenuhnya.
    | Selain itu, dapat dihidup-matikan secara dinamis melalui Pengaturan
    | Global Superadmin (system_settings key: 'simulation_mode_enabled').
    */
    'master_enabled' => (bool) env('SIMULATION_MODULE_ENABLED', true),
];

