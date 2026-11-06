<?php
// index.php - Dashboard Utama MiniERP
$pageTitle = 'Dashboard Penjualan';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

requireLogin();

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$currentMonth = date('Y-m');
$lastMonth = date('Y-m', strtotime('-1 month'));

// 1. KPI Hari Ini (DSH-01)
$todayStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as trx_count,
        COALESCE(SUM(total), 0) as total_sales,
        COALESCE(AVG(total), 0) as aov
    FROM transactions 
    WHERE DATE(created_at) = ? AND status = 'COMPLETED'
");
$todayStmt->execute([$today]);
$todayKpi = $todayStmt->fetch();

// KPI Kemarin (untuk perbandingan)
$yesterdayStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as trx_count,
        COALESCE(SUM(total), 0) as total_sales
    FROM transactions 
    WHERE DATE(created_at) = ? AND status = 'COMPLETED'
");
$yesterdayStmt->execute([$yesterday]);
$yesterdayKpi = $yesterdayStmt->fetch();

// KPI Bulan Ini
$monthStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as trx_count,
        COALESCE(SUM(total), 0) as total_sales
    FROM transactions 
    WHERE strftime('%Y-%m', created_at) = ? AND status = 'COMPLETED'
");
$monthStmt->execute([$currentMonth]);
$monthKpi = $monthStmt->fetch();

// Estimasi Laba Kotor Hari Ini (DSH-08)
// Laba Kotor = Total Jual - HPP (dari cost_price_snapshot)
$marginStmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(ti.line_total), 0) as total_revenue,
        COALESCE(SUM(ti.cost_price_snapshot * ti.qty), 0) as total_cogs
    FROM transaction_items ti
    JOIN transactions t ON t.id = ti.transaction_id
    WHERE DATE(t.created_at) = ? AND t.status = 'COMPLETED'
");
$marginStmt->execute([$today]);
$marginData = $marginStmt->fetch();
$grossProfitToday = $marginData['total_revenue'] - $marginData['total_cogs'];

// 2. Data Grafik Penjualan Harian Bulan Berjalan (DSH-03)
$dailyChartStmt = $pdo->prepare("
    SELECT 
        strftime('%d', created_at) as day_num,
        SUM(total) as daily_total
    FROM transactions
    WHERE strftime('%Y-%m', created_at) = ? AND status = 'COMPLETED'
    GROUP BY strftime('%d', created_at)
    ORDER BY day_num ASC
");
$dailyChartStmt->execute([$currentMonth]);
$dailyChartRaw = $dailyChartStmt->fetchAll();

$daysInMonth = (int)date('t');
$dailyLabels = [];
$dailyValues = [];
$dailyMap = [];
foreach ($dailyChartRaw as $d) {
    $dailyMap[(int)$d['day_num']] = (int)$d['daily_total'];
}
for ($i = 1; $i <= $daysInMonth; $i++) {
    $dailyLabels[] = "Tgl {$i}";
    $dailyValues[] = $dailyMap[$i] ?? 0;
}

// 3. Data Grafik Penjualan Bulanan (12 Bulan Terakhir) (DSH-02)
$monthlyChartStmt = $pdo->query("
    SELECT 
        strftime('%Y-%m', created_at) as month_key,
        SUM(total) as monthly_total
    FROM transactions
    WHERE status = 'COMPLETED'
    GROUP BY strftime('%Y-%m', created_at)
    ORDER BY month_key ASC
    LIMIT 12
");
$monthlyChartRaw = $monthlyChartStmt->fetchAll();
$monthlyLabels = [];
$monthlyValues = [];
foreach ($monthlyChartRaw as $m) {
    $monthlyLabels[] = date('M Y', strtotime($m['month_key'] . '-01'));
    $monthlyValues[] = (int)$m['monthly_total'];
}

// 4. Komposisi Metode Pembayaran (DSH-05)
$paymentStmt = $pdo->prepare("
    SELECT payment_method, COUNT(*) as count, SUM(total) as total
    FROM transactions
    WHERE strftime('%Y-%m', created_at) = ? AND status = 'COMPLETED'
    GROUP BY payment_method
");
$paymentStmt->execute([$currentMonth]);
$paymentData = $paymentStmt->fetchAll();
$payLabels = [];
$payValues = [];
foreach ($paymentData as $pd) {
    $payLabels[] = $pd['payment_method'];
    $payValues[] = (int)$pd['total'];
}

// 5. Top 5 Produk Terlaris (DSH-04)
$topProducts = $pdo->query("
    SELECT 
        ti.product_name_snapshot,
        SUM(ti.qty) as total_qty,
        SUM(ti.line_total) as total_sales
    FROM transaction_items ti
    JOIN transactions t ON t.id = ti.transaction_id
    WHERE t.status = 'COMPLETED'
    GROUP BY ti.product_name_snapshot
    ORDER BY total_qty DESC
    LIMIT 5
")->fetchAll();

// 6. Produk Stok Menipis (DSH-07)
$lowStockProducts = $pdo->query("
    SELECT id, sku, name, stock, min_stock, unit
    FROM products
    WHERE stock <= min_stock AND is_active = 1
    ORDER BY stock ASC
    LIMIT 5
")->fetchAll();
?>

<div class="d-flex justify-between align-center mb-16">
    <div>
        <h2 style="font-size: 16px; font-weight: 700;">Dashboard Ringkasan Usaha</h2>
        <p class="text-muted" style="font-size: 12px;">Performa penjualan real-time, grafik omzet, dan status persediaan barang.</p>
    </div>
    <div class="d-flex gap-8">
        <a href="pos.php" class="btn btn-primary">
            Kasir (POS)
        </a>
    </div>
</div>

<!-- Grid Kartu KPI (DSH-01 & DSH-08) -->
<div class="grid-kpi">
    <div class="kpi-card">
        <div class="kpi-label">Penjualan Hari Ini</div>
        <div class="kpi-value" style="color: var(--primary);"><?= format_rupiah($todayKpi['total_sales']) ?></div>
        <div class="kpi-sub">
            Kemarin: <?= format_rupiah($yesterdayKpi['total_sales']) ?>
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-label">Jumlah Transaksi Hari Ini</div>
        <div class="kpi-value"><?= $todayKpi['trx_count'] ?></div>
        <div class="kpi-sub">
            Kemarin: <?= $yesterdayKpi['trx_count'] ?> transaksi
        </div>
    </div>

    <div class="kpi-card">
        <div class="kpi-label">Rata-Rata Transaksi (AOV)</div>
        <div class="kpi-value"><?= format_rupiah($todayKpi['aov']) ?></div>
        <div class="kpi-sub">Rata-rata belanja per struk</div>
    </div>

    <div class="kpi-card">
        <div class="kpi-label">Penjualan Bulan Ini</div>
        <div class="kpi-value" style="color: var(--success);"><?= format_rupiah($monthKpi['total_sales']) ?></div>
        <div class="kpi-sub"><?= $monthKpi['trx_count'] ?> transaksi di <?= date('F Y') ?></div>
    </div>

    <div class="kpi-card">
        <div class="kpi-label">Estimasi Laba Kotor Hari Ini</div>
        <div class="kpi-value"><?= format_rupiah($grossProfitToday) ?></div>
        <div class="kpi-sub">Omzet − HPP snapshot</div>
    </div>
</div>

<!-- Baris Grafik: Harian & Bulanan -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px;">
    <!-- Grafik Penjualan Harian Bulan Ini -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <span class="card-title">Grafik Tren Penjualan Harian (<?= date('F Y') ?>)</span>
            <span class="badge badge-secondary"><?= $daysInMonth ?> Hari</span>
        </div>
        <div class="card-body">
            <div style="height: 250px;">
                <canvas id="dailySalesChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Grafik Komposisi Metode Bayar -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <span class="card-title">Metode Pembayaran</span>
        </div>
        <div class="card-body">
            <div style="height: 250px; display: flex; align-items: center; justify-content: center;">
                <?php if (empty($payValues)): ?>
                    <div class="text-muted" style="font-size: 12px;">Belum ada data transaksi bulan ini.</div>
                <?php else: ?>
                    <canvas id="paymentMethodChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Baris Kedua: Top Produk & Widget Stok Menipis -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
    <!-- Top 5 Produk Terlaris (DSH-04) -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <span class="card-title">Top 5 Produk Terlaris</span>
            <a href="reports.php" class="btn btn-secondary btn-sm">Lihat Semua</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama Produk</th>
                        <th class="text-center">Terjual</th>
                        <th class="text-right">Total Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topProducts)): ?>
                        <tr><td colspan="3" class="text-center text-muted" style="padding: 16px;">Belum ada data penjualan tercatat.</td></tr>
                    <?php else: ?>
                        <?php foreach ($topProducts as $idx => $tp): ?>
                            <tr>
                                <td>
                                    <span style="font-weight: 700; color: #94a3b8; margin-right: 6px;"><?= $idx + 1 ?>.</span>
                                    <span class="fw-bold"><?= htmlspecialchars($tp['product_name_snapshot']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-secondary"><?= $tp['total_qty'] ?> unit</span>
                                </td>
                                <td class="text-right fw-bold"><?= format_rupiah($tp['total_sales']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Widget Stok Menipis (DSH-07) -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <span class="card-title" style="color: var(--danger);">Peringatan Stok Menipis</span>
            <a href="stock-in.php" class="btn btn-primary btn-sm">Restock Barang</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>SKU / Produk</th>
                        <th class="text-center">Sisa Stok</th>
                        <th class="text-center">Batas Min</th>
                        <th class="text-right">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lowStockProducts)): ?>
                        <tr><td colspan="4" class="text-center text-muted" style="padding: 16px;">Semua persediaan produk dalam batas aman.</td></tr>
                    <?php else: ?>
                        <?php foreach ($lowStockProducts as $lp): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($lp['name']) ?></div>
                                    <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($lp['sku']) ?></div>
                                </td>
                                <td class="text-center fw-bold <?= $lp['stock'] <= 0 ? 'text-danger' : 'text-warning' ?>">
                                    <?= $lp['stock'] ?> <?= htmlspecialchars($lp['unit']) ?>
                                </td>
                                <td class="text-center text-muted">
                                    <?= $lp['min_stock'] ?> <?= htmlspecialchars($lp['unit']) ?>
                                </td>
                                <td class="text-right">
                                    <?php if ($lp['stock'] <= 0): ?>
                                        <span class="badge badge-danger">Habis</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Menipis</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Inisialisasi Grafik Penjualan Harian
const dailyCtx = document.getElementById('dailySalesChart').getContext('2d');
new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($dailyLabels) ?>,
        datasets: [{
            label: 'Omzet Harian (Rp)',
            data: <?= json_encode($dailyValues) ?>,
            borderColor: '#1e40af',
            backgroundColor: 'rgba(30, 64, 175, 0.08)',
            borderWidth: 2,
            tension: 0.2,
            fill: true,
            pointRadius: 2,
            pointHoverRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(val) {
                        return 'Rp ' + Number(val).toLocaleString('id-ID');
                    },
                    font: { size: 11 }
                }
            },
            x: {
                ticks: { font: { size: 10 } }
            }
        }
    }
});

// Inisialisasi Grafik Metode Bayar
<?php if (!empty($payValues)): ?>
const payCtx = document.getElementById('paymentMethodChart').getContext('2d');
new Chart(payCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($payLabels) ?>,
        datasets: [{
            data: <?= json_encode($payValues) ?>,
            backgroundColor: ['#1e40af', '#15803d', '#b45309', '#0369a1'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { boxWidth: 12, font: { size: 11 } }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
