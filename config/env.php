<?php
// config/env.php - Konfigurasi Database MiniERP

// Pilihan driver: 'sqlite' (bawaan lokal) atau 'supabase' (PostgreSQL cloud)
define('DB_DRIVER', 'supabase');

// ========================================================
// Konfigurasi Supabase (PostgreSQL)
// Hanya digunakan jika DB_DRIVER diset ke 'supabase'
// ========================================================
// Menggunakan Supabase Connection Pooler (IPv4 & IPv6 kompatibel)
define('SUPABASE_HOST', 'aws-0-ap-northeast-2.pooler.supabase.com');
define('SUPABASE_PORT', 6543);
define('SUPABASE_DB',   'postgres');
define('SUPABASE_USER', 'postgres.nnvzxpsobydmdozoybcl');
define('SUPABASE_PASS', 'zismEj-tadqew-0kidje');
