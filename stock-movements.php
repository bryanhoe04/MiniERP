<?php
// stock-movements.php - Riwayat Pergerakan Stok (Stock Movement Log)
$pageTitle = 'Riwayat Pergerakan Stok';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

requireRole(['admin', 'gudang']);

$productId = (int)($_GET['product_id'] ?? 0);
$type = trim($_GET['type'] ?? '');
$date = trim($_GET['date'] ?? '');

$where = ["1=1"];
$params = [];

if ($productId > 0) {
    $where[] = "sm.product_id = ?";
    $params[] = $productId;
}

if (!empty($type)) {
    $where[] = "sm.type = ?";
    $params[] = $type;
}

if (!empty($date)) {
    $where[] = "DATE(sm.created_at) = ?";
    $params[] = $date;
}

$whereClause = implode(" AND ", $where);

// Daftar produk untuk filter
$products = $pdo->query("SELECT id, sku, name FROM products ORDER BY name ASC")->fetchAll();

// Query pergerakan stok
$sql = "
    SELECT sm.*, p.sku, p.name as product_name, p.unit, u.name as user_name
    FROM stock_movements sm
    LEFT JOIN products p ON p.id = sm.product_id
    LEFT JOIN users u ON u.id = sm.user_id
    WHERE {$whereClause}
    ORDER BY sm.id DESC
    LIMIT 200
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll();
?>

<div class="d-flex justify-between align-center mb-16">
    <div>
        <h2 style="font-size: 16px; font-weight: 700;">Riwayat Pergerakan Stok</h2>
        <p class="text-muted" style="font-size: 12px;">Audit log setiap perubahan persediaan barang (penjualan, stok masuk, penyesuaian, dan retur).</p>
    </div>
    <a href="stock-in.php" class="btn btn-primary">
        + Input Stok Masuk
    </a>
</div>

<!-- Filter Bar -->
<div class="card mb-16">
    <div class="card-body" style="padding: 12px;">
        <form method="GET" action="stock-movements.php" class="form-inline">
            <div style="flex: 2; min-width: 200px;">
                <select name="product_id" class="form-control">
                    <option value="">Semua Produk</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $productId === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['sku']) ?> - <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1; min-width: 140px;">
                <select name="type" class="form-control">
                    <option value="">Semua Tipe</option>
                    <option value="SALE" <?= $type === 'SALE' ? 'selected' : '' ?>>SALE (Penjualan)</option>
                    <option value="STOCK_IN" <?= $type === 'STOCK_IN' ? 'selected' : '' ?>>STOCK IN (Masuk)</option>
                    <option value="ADJUSTMENT" <?= $type === 'ADJUSTMENT' ? 'selected' : '' ?>>ADJUSTMENT (Opname)</option>
                    <option value="VOID_RETURN" <?= $type === 'VOID_RETURN' ? 'selected' : '' ?>>VOID RETURN (Batal)</option>
                </select>
            </div>
            <div style="flex: 1; min-width: 130px;">
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>">
            </div>
            <button type="submit" class="btn btn-secondary">Filter</button>
            <?php if ($productId > 0 || !empty($type) || !empty($date)): ?>
                <a href="stock-movements.php" class="btn btn-secondary" style="color: var(--text-muted);">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 150px;">Waktu & Tanggal</th>
                    <th>Nama Produk</th>
                    <th style="width: 130px;" class="text-center">Tipe Transaksi</th>
                    <th style="width: 110px;" class="text-center">Perubahan</th>
                    <th style="width: 110px;" class="text-center">Stok Akhir</th>
                    <th style="width: 140px;">No. Referensi</th>
                    <th>Petugas</th>
                    <th>Catatan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted" style="padding: 24px;">Belum ada riwayat pergerakan stok tercatat.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movements as $m): ?>
                        <tr>
                            <td class="text-muted"><?= format_tanggal($m['created_at'], true) ?></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($m['product_name'] ?? 'Produk Dihapus') ?></div>
                                <div class="text-muted" style="font-size: 11px;">SKU: <?= htmlspecialchars($m['sku'] ?? '-') ?></div>
                            </td>
                            <td class="text-center">
                                <?php if ($m['type'] === 'STOCK_IN'): ?>
                                    <span class="badge badge-success">STOCK IN</span>
                                <?php elseif ($m['type'] === 'SALE'): ?>
                                    <span class="badge badge-info">SALE</span>
                                <?php elseif ($m['type'] === 'VOID_RETURN'): ?>
                                    <span class="badge badge-warning">VOID RETURN</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">ADJUSTMENT</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-bold <?= $m['qty_change'] > 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $m['qty_change'] > 0 ? '+' . $m['qty_change'] : $m['qty_change'] ?> <?= htmlspecialchars($m['unit'] ?? '') ?>
                            </td>
                            <td class="text-center fw-bold">
                                <?= $m['stock_after'] ?> <?= htmlspecialchars($m['unit'] ?? '') ?>
                            </td>
                            <td>
                                <code><?= htmlspecialchars($m['reference'] ?: '-') ?></code>
                            </td>
                            <td><?= htmlspecialchars($m['user_name'] ?? 'Sistem') ?></td>
                            <td class="text-muted" style="font-size: 12px;"><?= htmlspecialchars($m['note'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
