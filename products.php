<?php
// products.php - Manajemen Daftar Produk
$pageTitle = 'Daftar Produk';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

requireRole(['admin', 'gudang']);

// Handle Hapus Produk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        set_flash('success', 'Produk berhasil dihapus.');
        header('Location: products.php');
        exit;
    }
}

// Filter & Pencarian
$search = trim($_GET['search'] ?? '');
$categoryId = (int)($_GET['category_id'] ?? 0);
$stockStatus = trim($_GET['stock_status'] ?? ''); // 'aman', 'menipis', 'habis'

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($categoryId > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $categoryId;
}

if ($stockStatus === 'habis') {
    $where[] = "p.stock <= 0";
} elseif ($stockStatus === 'menipis') {
    $where[] = "p.stock > 0 AND p.stock <= p.min_stock";
} elseif ($stockStatus === 'aman') {
    $where[] = "p.stock > p.min_stock";
}

$whereClause = implode(" AND ", $where);

// Ambil Kategori untuk dropdown filter
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Query Produk
$sql = "
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE {$whereClause}
    ORDER BY p.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Hitung statistik ringkas produk
$totalProducts = count($products);
$lowStockCount = 0;
$outOfStockCount = 0;

foreach ($products as $p) {
    if ($p['stock'] <= 0) {
        $outOfStockCount++;
    } elseif ($p['stock'] <= $p['min_stock']) {
        $lowStockCount++;
    }
}
?>

<div class="d-flex justify-between align-center mb-16">
    <div>
        <h2 style="font-size: 16px; font-weight: 700;">Daftar Produk</h2>
        <p class="text-muted" style="font-size: 12px;">Kelola master data produk, harga jual, dan persediaan.</p>
    </div>
    <div class="d-flex gap-8">
        <a href="product-form.php" class="btn btn-primary">
            Tambah Produk
        </a>
    </div>
</div>

<!-- Filter Bar -->
<div class="card mb-16">
    <div class="card-body" style="padding: 12px;">
        <form method="GET" action="products.php" class="form-inline">
            <div style="flex: 2; min-width: 200px;">
                <input type="text" name="search" class="form-control" placeholder="Cari nama produk, SKU, barcode..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div style="flex: 1; min-width: 150px;">
                <select name="category_id" class="form-control">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1; min-width: 140px;">
                <select name="stock_status" class="form-control">
                    <option value="">Semua Status Stok</option>
                    <option value="aman" <?= $stockStatus === 'aman' ? 'selected' : '' ?>>Stok Aman</option>
                    <option value="menipis" <?= $stockStatus === 'menipis' ? 'selected' : '' ?>>Stok Menipis</option>
                    <option value="habis" <?= $stockStatus === 'habis' ? 'selected' : '' ?>>Stok Habis</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">Filter</button>
            <?php if (!empty($search) || $categoryId > 0 || !empty($stockStatus)): ?>
                <a href="products.php" class="btn btn-secondary" style="color: var(--text-muted);">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Info Alert Ringkas -->
<?php if ($lowStockCount > 0 || $outOfStockCount > 0): ?>
    <div class="alert alert-warning mb-16" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            Pemberitahuan Persediaan: <strong><?= $lowStockCount ?></strong> produk menipis dan <strong><?= $outOfStockCount ?></strong> produk habis.
        </div>
        <a href="stock-in.php" class="btn btn-sm btn-primary">Buka Stock In</a>
    </div>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 110px;">SKU / Barcode</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th class="text-right">Harga Beli</th>
                    <th class="text-right">Harga Jual (Inc. PPN)</th>
                    <th class="text-center" style="width: 110px;">Stok / Min</th>
                    <th class="text-center" style="width: 100px;">Status Stok</th>
                    <th class="text-right" style="width: 130px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted" style="padding: 24px;">Tidak ada produk yang cocok dengan pencarian / filter.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($p['sku']) ?></div>
                                <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($p['barcode'] ?: '-') ?></div>
                            </td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="text-muted" style="font-size: 11px;">Satuan: <?= htmlspecialchars($p['unit']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($p['category_name'] ?? 'Umum') ?></td>
                            <td class="text-right text-muted"><?= format_rupiah($p['cost_price']) ?></td>
                            <td class="text-right fw-bold"><?= format_rupiah($p['sell_price']) ?></td>
                            <td class="text-center">
                                <span class="fw-bold"><?= $p['stock'] ?></span> / <span class="text-muted"><?= $p['min_stock'] ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($p['stock'] <= 0): ?>
                                    <span class="badge badge-danger">Habis</span>
                                <?php elseif ($p['stock'] <= $p['min_stock']): ?>
                                    <span class="badge badge-warning">Menipis</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Aman</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <a href="product-form.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                                <form method="POST" action="products.php" style="display: inline-block;" onsubmit="return confirm('Hapus produk <?= htmlspecialchars(addslashes($p['name'])) ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--danger);">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
