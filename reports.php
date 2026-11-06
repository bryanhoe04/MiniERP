<?php
// reports.php - Laporan Penjualan Harian & Bulanan + Export CSV
$pageTitle = 'Laporan Penjualan';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireRole(['admin', 'gudang']);

// Tab: daily (harian) atau monthly (bulanan)
$tab = $_GET['tab'] ?? 'daily';
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedMonth = $_GET['month'] ?? date('Y-m');
$selectedCashier = (int)($_GET['cashier_id'] ?? 0);

// Daftar Kasir untuk filter
$cashiers = $pdo->query("SELECT id, name FROM users WHERE role IN ('admin', 'kasir') ORDER BY name ASC")->fetchAll();

// --- LOGIKA EXPORT CSV (RPT-06) ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "Laporan_Penjualan_{$tab}_" . ($tab === 'daily' ? $selectedDate : $selectedMonth) . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');
    // Header CSV
    fputcsv($output, ['No. Struk', 'Waktu', 'Kasir', 'Metode Bayar', 'Subtotal', 'Diskon', 'PPN', 'Total', 'Status']);

    if ($tab === 'daily') {
        $whereExp = ["DATE(t.created_at) = ?"];
        $paramsExp = [$selectedDate];
        if ($selectedCashier > 0) {
            $whereExp[] = "t.user_id = ?";
            $paramsExp[] = $selectedCashier;
        }
        $sqlExp = "
            SELECT r.receipt_code, t.created_at, u.name as cashier_name,
                   t.payment_method, t.subtotal, t.discount, t.tax, t.total, t.status
            FROM transactions t
            LEFT JOIN receipts r ON r.transaction_id = t.id
            LEFT JOIN users u ON u.id = t.user_id
            WHERE " . implode(" AND ", $whereExp) . "
            ORDER BY t.id ASC
        ";
        $stmtExp = $pdo->prepare($sqlExp);
        $stmtExp->execute($paramsExp);
        while ($row = $stmtExp->fetch()) {
            fputcsv($output, [
                $row['receipt_code'],
                date('H:i:s', strtotime($row['created_at'])),
                $row['cashier_name'],
                $row['payment_method'],
                $row['subtotal'],
                $row['discount'],
                $row['tax'],
                $row['total'],
                $row['status']
            ]);
        }
    } else {
        // Bulanan
        $sqlExp = "
            SELECT r.receipt_code, t.created_at, u.name as cashier_name,
                   t.payment_method, t.subtotal, t.discount, t.tax, t.total, t.status
            FROM transactions t
            LEFT JOIN receipts r ON r.transaction_id = t.id
            LEFT JOIN users u ON u.id = t.user_id
            WHERE strftime('%Y-%m', t.created_at) = ?
            ORDER BY t.id ASC
        ";
        $stmtExp = $pdo->prepare($sqlExp);
        $stmtExp->execute([$selectedMonth]);
        while ($row = $stmtExp->fetch()) {
            fputcsv($output, [
                $row['receipt_code'],
                date('Y-m-d H:i', strtotime($row['created_at'])),
                $row['cashier_name'],
                $row['payment_method'],
                $row['subtotal'],
                $row['discount'],
                $row['tax'],
                $row['total'],
                $row['status']
            ]);
        }
    }
    fclose($output);
    exit;
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// --- DATA LAPORAN HARIAN ---
if ($tab === 'daily') {
    $where = ["DATE(t.created_at) = ?"];
    $params = [$selectedDate];

    if ($selectedCashier > 0) {
        $where[] = "t.user_id = ?";
        $params[] = $selectedCashier;
    }

    $whereClause = implode(" AND ", $where);

    // 1. Ringkasan Harian (RPT-02)
    $summaryStmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) as completed_count,
            COUNT(CASE WHEN status = 'VOIDED' THEN 1 END) as voided_count,
            COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN subtotal ELSE 0 END), 0) as total_subtotal,
            COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN discount ELSE 0 END), 0) as total_discount,
            COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN tax ELSE 0 END), 0) as total_tax,
            COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN total ELSE 0 END), 0) as net_sales,
            COALESCE(SUM(CASE WHEN status = 'VOIDED' THEN total ELSE 0 END), 0) as total_voided_amount
        FROM transactions t
        WHERE {$whereClause}
    ");
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch();

    // 2. Rekap per Metode Pembayaran (RPT-03)
    $methodsStmt = $pdo->prepare("
        SELECT payment_method, COUNT(*) as trx_count, SUM(total) as method_total
        FROM transactions t
        WHERE {$whereClause} AND status = 'COMPLETED'
        GROUP BY payment_method
        ORDER BY method_total DESC
    ");
    $methodsStmt->execute($params);
    $paymentSummary = $methodsStmt->fetchAll();

    // 3. Daftar Transaksi Harian (RPT-01)
    $listStmt = $pdo->prepare("
        SELECT t.*, r.receipt_code, u.name as cashier_name
        FROM transactions t
        LEFT JOIN receipts r ON r.transaction_id = t.id
        LEFT JOIN users u ON u.id = t.user_id
        WHERE {$whereClause}
        ORDER BY t.id DESC
    ");
    $listStmt->execute($params);
    $transactions = $listStmt->fetchAll();

    // 4. Rekap Produk Terjual Hari Ini (RPT-04)
    $prodStmt = $pdo->prepare("
        SELECT ti.product_name_snapshot, SUM(ti.qty) as total_qty, SUM(ti.line_total) as total_omzet
        FROM transaction_items ti
        JOIN transactions t ON t.id = ti.transaction_id
        WHERE {$whereClause} AND t.status = 'COMPLETED'
        GROUP BY ti.product_name_snapshot
        ORDER BY total_qty DESC
        LIMIT 10
    ");
    $prodStmt->execute($params);
    $topProducts = $prodStmt->fetchAll();
} else {
    // --- DATA LAPORAN BULANAN (RPT-07) ---
    // Rekap per hari dalam satu bulan
    $monthlyStmt = $pdo->prepare("
        SELECT 
            DATE(t.created_at) as sale_date,
            COUNT(CASE WHEN status = 'COMPLETED' THEN 1 END) as total_trx,
            COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN total ELSE 0 END), 0) as omzet,
            COALESCE(SUM(CASE WHEN status = 'COMPLETED' THEN tax ELSE 0 END), 0) as total_tax,
            COUNT(CASE WHEN status = 'VOIDED' THEN 1 END) as void_count
        FROM transactions t
        WHERE strftime('%Y-%m', t.created_at) = ?
        GROUP BY DATE(t.created_at)
        ORDER BY sale_date ASC
    ");
    $monthlyStmt->execute([$selectedMonth]);
    $dailyBreakdown = $monthlyStmt->fetchAll();

    $monthTotalOmzet = 0;
    $monthTotalTrx = 0;
    $monthTotalTax = 0;
    foreach ($dailyBreakdown as $row) {
        $monthTotalOmzet += $row['omzet'];
        $monthTotalTrx += $row['total_trx'];
        $monthTotalTax += $row['total_tax'];
    }
}
?>

<div class="d-flex justify-between align-center mb-16">
    <div>
        <h2 style="font-size: 16px; font-weight: 700;">Laporan Penjualan</h2>
        <p class="text-muted" style="font-size: 12px;">Rekap penjualan harian, bulanan, rekonsiliasi kas, dan export data.</p>
    </div>
    <div class="d-flex gap-8">
        <a href="reports.php?tab=<?= $tab ?>&date=<?= $selectedDate ?>&month=<?= $selectedMonth ?>&cashier_id=<?= $selectedCashier ?>&export=csv" class="btn btn-secondary">
            Unduh CSV
        </a>
    </div>
</div>

<!-- Tab Navigasi -->
<div style="display: flex; gap: 4px; margin-bottom: 16px; border-bottom: 1px solid var(--border-light); padding-bottom: 8px;">
    <a href="reports.php?tab=daily&date=<?= $selectedDate ?>" class="btn <?= $tab === 'daily' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
        Laporan Harian
    </a>
    <a href="reports.php?tab=monthly&month=<?= $selectedMonth ?>" class="btn <?= $tab === 'monthly' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
        Laporan Bulanan
    </a>
</div>

<!-- Filter Bar -->
<div class="card mb-16">
    <div class="card-body" style="padding: 12px;">
        <form method="GET" action="reports.php" class="form-inline">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            
            <?php if ($tab === 'daily'): ?>
                <div>
                    <label class="form-label" style="font-size: 11px;">Pilih Tanggal</label>
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>">
                </div>
                <div>
                    <label class="form-label" style="font-size: 11px;">Filter Kasir</label>
                    <select name="cashier_id" class="form-control">
                        <option value="">Semua Kasir</option>
                        <?php foreach ($cashiers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $selectedCashier === (int)$c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div>
                    <label class="form-label" style="font-size: 11px;">Pilih Bulan</label>
                    <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($selectedMonth) ?>">
                </div>
            <?php endif; ?>

            <div style="align-self: flex-end;">
                <button type="submit" class="btn btn-secondary">Tampilkan Laporan</button>
            </div>
        </form>
    </div>
</div>

<?php if ($tab === 'daily'): ?>
    <!-- KPI Ringkasan Harian -->
    <div class="grid-kpi">
        <div class="kpi-card">
            <div class="kpi-label">Penjualan Bersih (Omzet)</div>
            <div class="kpi-value" style="color: var(--primary);"><?= format_rupiah($summary['net_sales']) ?></div>
            <div class="kpi-sub">Total transaksi berhasil</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Jumlah Transaksi</div>
            <div class="kpi-value"><?= $summary['completed_count'] ?></div>
            <div class="kpi-sub">Transaksi selesai</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total Pajak PPN (11%)</div>
            <div class="kpi-value text-muted"><?= format_rupiah($summary['total_tax']) ?></div>
            <div class="kpi-sub">Porsi pajak terkandung</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Transaksi Dibatalkan (Void)</div>
            <div class="kpi-value text-danger"><?= $summary['voided_count'] ?></div>
            <div class="kpi-sub"><?= format_rupiah($summary['total_voided_amount']) ?></div>
        </div>
    </div>

    <!-- Rekap Metode Bayar & Top Produk -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
        <!-- Rekap Metode Bayar (RPT-03) -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <span class="card-title">Rekonsiliasi per Metode Bayar</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Metode Pembayaran</th>
                            <th class="text-center">Jumlah Trx</th>
                            <th class="text-right">Total Uang Masuk</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paymentSummary)): ?>
                            <tr><td colspan="3" class="text-center text-muted" style="padding: 16px;">Belum ada penjualan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($paymentSummary as $ps): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($ps['payment_method']) ?></td>
                                    <td class="text-center"><?= $ps['trx_count'] ?></td>
                                    <td class="text-right fw-bold"><?= format_rupiah($ps['method_total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Produk Hari Ini (RPT-04) -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <span class="card-title">Top 10 Produk Terlaris Hari Ini</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama Produk</th>
                            <th class="text-center">Qty Terjual</th>
                            <th class="text-right">Total Omzet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topProducts)): ?>
                            <tr><td colspan="3" class="text-center text-muted" style="padding: 16px;">Belum ada produk terjual.</td></tr>
                        <?php else: ?>
                            <?php foreach ($topProducts as $tp): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($tp['product_name_snapshot']) ?></td>
                                    <td class="text-center"><span class="badge badge-secondary"><?= $tp['total_qty'] ?></span></td>
                                    <td class="text-right"><?= format_rupiah($tp['total_omzet']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Rincian Daftar Transaksi Harian (RPT-01) -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Daftar Transaksi Tanggal <?= format_tanggal($selectedDate) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 170px;">No. Struk</th>
                        <th style="width: 100px;">Jam</th>
                        <th>Kasir</th>
                        <th>Metode Bayar</th>
                        <th class="text-right">Subtotal</th>
                        <th class="text-right">Diskon</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr><td colspan="8" class="text-center text-muted" style="padding: 24px;">Tidak ada transaksi pada tanggal ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tr): ?>
                            <tr>
                                <td>
                                    <a href="receipt.php?code=<?= urlencode($tr['receipt_code']) ?>" target="_blank" class="fw-bold" style="color: var(--primary); text-decoration: none;">
                                        <?= htmlspecialchars($tr['receipt_code']) ?>
                                    </a>
                                </td>
                                <td class="text-muted"><?= date('H:i:s', strtotime($tr['created_at'])) ?></td>
                                <td><?= htmlspecialchars($tr['cashier_name'] ?? 'Kasir') ?></td>
                                <td><span class="badge badge-secondary"><?= htmlspecialchars($tr['payment_method']) ?></span></td>
                                <td class="text-right text-muted"><?= format_rupiah($tr['subtotal']) ?></td>
                                <td class="text-right text-muted"><?= $tr['discount'] > 0 ? '-' . format_rupiah($tr['discount']) : '-' ?></td>
                                <td class="text-right fw-bold"><?= format_rupiah($tr['total']) ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $tr['status'] === 'COMPLETED' ? 'badge-success' : 'badge-danger' ?>">
                                        <?= htmlspecialchars($tr['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- LAPORAN BULANAN -->
    <div class="grid-kpi">
        <div class="kpi-card">
            <div class="kpi-label">Total Omzet Bulan Ini</div>
            <div class="kpi-value" style="color: var(--primary);"><?= format_rupiah($monthTotalOmzet) ?></div>
            <div class="kpi-sub">Bulan <?= htmlspecialchars($selectedMonth) ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total Transaksi Selesai</div>
            <div class="kpi-value"><?= $monthTotalTrx ?></div>
            <div class="kpi-sub">Sepanjang bulan</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Porsi Pajak PPN (11%)</div>
            <div class="kpi-value text-muted"><?= format_rupiah($monthTotalTax) ?></div>
            <div class="kpi-sub">Terkandung dalam omzet</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Rekap Harian Bulan <?= htmlspecialchars($selectedMonth) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th class="text-center">Jumlah Transaksi</th>
                        <th class="text-center">Transaksi Void</th>
                        <th class="text-right">PPN Terkandung</th>
                        <th class="text-right">Total Omzet Bersih</th>
                        <th class="text-right" style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dailyBreakdown)): ?>
                        <tr><td colspan="6" class="text-center text-muted" style="padding: 24px;">Tidak ada transaksi di bulan ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dailyBreakdown as $db): ?>
                            <tr>
                                <td class="fw-bold"><?= format_tanggal($db['sale_date']) ?></td>
                                <td class="text-center"><?= $db['total_trx'] ?></td>
                                <td class="text-center text-danger"><?= $db['void_count'] ?></td>
                                <td class="text-right text-muted"><?= format_rupiah($db['total_tax']) ?></td>
                                <td class="text-right fw-bold"><?= format_rupiah($db['omzet']) ?></td>
                                <td class="text-right">
                                    <a href="reports.php?tab=daily&date=<?= $db['sale_date'] ?>" class="btn btn-secondary btn-sm">
                                        Rincian
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
