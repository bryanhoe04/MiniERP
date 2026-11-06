<?php
// test_connection.php - Skrip Uji Koneksi Database (Supabase / SQLite)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uji Koneksi Database - MiniERP</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="background: #f1f5f9; padding: 30px;">
    <div class="card" style="max-width: 650px; margin: 0 auto; padding: 24px;">
        <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 8px;">Status Koneksi Database</h2>
        <p class="text-muted" style="font-size: 13px; margin-bottom: 20px;">Memeriksa koneksi aktif sistem MiniERP.</p>

        <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: var(--radius-sm); padding: 16px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span class="text-muted">Driver Aktif:</span>
                <strong><?= strtoupper(htmlspecialchars($driver)) ?></strong>
            </div>

            <?php if ($driver === 'supabase' || $driver === 'pgsql'): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span class="text-muted">Host Supabase:</span>
                    <code><?= htmlspecialchars(SUPABASE_HOST) ?></code>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span class="text-muted">Database:</span>
                    <code><?= htmlspecialchars(SUPABASE_DB) ?></code>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span class="text-muted">Port:</span>
                    <code><?= htmlspecialchars((string)SUPABASE_PORT) ?></code>
                </div>
            <?php else: ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span class="text-muted">Tipe Database:</span>
                    <span>SQLite Lokal (<code>database/database.sqlite</code>)</span>
                </div>
            <?php endif; ?>
        </div>

        <?php
        try {
            $productCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
            $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $trxCount = $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
        ?>
            <div class="alert alert-success" style="margin-bottom: 16px;">
                <strong>Koneksi Berhasil!</strong> Database terhubung dan tabel-tabel dapat diakses dengan baik.
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 20px;">
                <div class="kpi-card" style="text-align: center;">
                    <div class="kpi-label">Total Produk</div>
                    <div class="kpi-value"><?= $productCount ?></div>
                </div>
                <div class="kpi-card" style="text-align: center;">
                    <div class="kpi-label">Pengguna</div>
                    <div class="kpi-value"><?= $userCount ?></div>
                </div>
                <div class="kpi-card" style="text-align: center;">
                    <div class="kpi-label">Transaksi</div>
                    <div class="kpi-value"><?= $trxCount ?></div>
                </div>
            </div>
        <?php
        } catch (Exception $e) {
        ?>
            <div class="alert alert-danger" style="margin-bottom: 16px;">
                <strong>Koneksi Gagal:</strong> <?= htmlspecialchars($e->getMessage()) ?>
            </div>
            <p style="font-size: 12px; color: var(--text-muted);">
                Pastikan Anda telah menjalankan skrip <code>database/supabase_schema.sql</code> di SQL Editor Supabase dan kredensial di <code>config/env.php</code> sudah sesuai.
            </p>
        <?php
        }
        ?>

        <div style="text-align: right; margin-top: 10px;">
            <a href="index.php" class="btn btn-primary">Buka Dashboard</a>
        </div>
    </div>
</body>
</html>
