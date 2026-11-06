<?php
// product-form.php - Form Tambah / Edit Produk
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

requireRole(['admin', 'gudang']);

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$pageTitle = $isEdit ? 'Edit Produk' : 'Tambah Produk Baru';

$product = [
    'sku' => '',
    'barcode' => '',
    'name' => '',
    'category_id' => '',
    'cost_price' => 0,
    'sell_price' => 0,
    'stock' => 0,
    'min_stock' => 5,
    'unit' => 'pcs',
    'is_active' => 1
];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        set_flash('error', 'Produk tidak ditemukan.');
        header('Location: products.php');
        exit;
    }
    $product = $found;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = trim($_POST['sku'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $costPrice = (int)($_POST['cost_price'] ?? 0);
    $sellPrice = (int)($_POST['sell_price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $minStock = (int)($_POST['min_stock'] ?? 5);
    $unit = trim($_POST['unit'] ?? 'pcs');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Auto-generate SKU jika dikosongkan
    if (empty($sku)) {
        $sku = 'PRD-' . strtoupper(substr(uniqid(), -6));
    }

    if (empty($name)) {
        $errors[] = 'Nama produk wajib diisi.';
    }

    if ($sellPrice < 0 || $costPrice < 0) {
        $errors[] = 'Harga tidak boleh negatif.';
    }

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $sql = "
                    UPDATE products SET 
                        sku = ?, barcode = ?, name = ?, category_id = ?,
                        cost_price = ?, sell_price = ?, min_stock = ?,
                        unit = ?, is_active = ?
                    WHERE id = ?
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $sku, $barcode, $name, $categoryId,
                    $costPrice, $sellPrice, $minStock,
                    $unit, $isActive, $id
                ]);
                set_flash('success', 'Produk berhasil diperbarui.');
            } else {
                $sql = "
                    INSERT INTO products (
                        sku, barcode, name, category_id,
                        cost_price, sell_price, stock, min_stock,
                        unit, is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $sku, $barcode, $name, $categoryId,
                    $costPrice, $sellPrice, $stock, $minStock,
                    $unit, $isActive
                ]);
                $newId = $pdo->lastInsertId();

                // Catat ke stock movements jika ada stok awal
                if ($stock > 0) {
                    $movStmt = $pdo->prepare("
                        INSERT INTO stock_movements (product_id, type, qty_change, stock_after, reference, user_id, note)
                        VALUES (?, 'ADJUSTMENT', ?, ?, 'STOK AWAL', ?, 'Inisialisasi produk baru')
                    ");
                    $movStmt->execute([$newId, $stock, $stock, $currentUser['id']]);
                }

                set_flash('success', 'Produk baru berhasil ditambahkan.');
            }
            header('Location: products.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Gagal menyimpan: SKU mungkin sudah digunakan oleh produk lain.';
        }
    }
}
?>

<div class="d-flex justify-between align-center mb-16">
    <div>
        <h2 style="font-size: 16px; font-weight: 700;"><?= $isEdit ? 'Edit Produk: ' . htmlspecialchars($product['name']) : 'Tambah Produk Baru' ?></h2>
        <p class="text-muted" style="font-size: 12px;">Lengkapi data spesifikasi produk retail.</p>
    </div>
    <a href="products.php" class="btn btn-secondary">Kembali ke Daftar</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger mb-16">
        <?php foreach ($errors as $err): ?>
            <div>• <?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 800px;">
    <div class="card-body">
        <form method="POST" action="product-form.php<?= $isEdit ? '?id=' . $id : '' ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Kode SKU (Unik)</label>
                    <input type="text" name="sku" class="form-control" placeholder="Contoh: PRD-001 (Kosongkan utk auto)" value="<?= htmlspecialchars($product['sku']) ?>">
                    <span class="text-muted" style="font-size: 11px;">Biarkan kosong jika ingin sistem membuat otomatis.</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Kode Barcode</label>
                    <input type="text" name="barcode" class="form-control" placeholder="Nomor barcode / scan" value="<?= htmlspecialchars($product['barcode']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Nama Produk *</label>
                <input type="text" name="name" class="form-control" placeholder="Contoh: Minyak Goreng 2L, Indomie Goreng" value="<?= htmlspecialchars($product['name']) ?>" required>
            </div>

            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Kategori Produk</label>
                    <select name="category_id" class="form-control">
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Satuan</label>
                    <input type="text" name="unit" class="form-control" placeholder="pcs, btl, box, kg..." value="<?= htmlspecialchars($product['unit']) ?>" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label class="form-label">Harga Beli / Pokok (Rp)</label>
                    <input type="number" name="cost_price" class="form-control" min="0" value="<?= $product['cost_price'] ?>" required>
                    <span class="text-muted" style="font-size: 11px;">Digunakan untuk estimasi laba kotor & HPP.</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Harga Jual Konsumen (Rp) *</label>
                    <input type="number" name="sell_price" class="form-control" min="0" value="<?= $product['sell_price'] ?>" required>
                    <span class="text-muted" style="font-size: 11px;"><strong>Sudah Termasuk PPN 11%</strong> (tax-inclusive).</span>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <?php if (!$isEdit): ?>
                    <div class="form-group">
                        <label class="form-label">Stok Awal</label>
                        <input type="number" name="stock" class="form-control" min="0" value="<?= $product['stock'] ?>">
                        <span class="text-muted" style="font-size: 11px;">Stok awal akan dicatat di riwayat stok.</span>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label class="form-label">Stok Saat Ini (Hanya Lihat)</label>
                        <input type="text" class="form-control" value="<?= $product['stock'] ?> <?= htmlspecialchars($product['unit']) ?>" disabled>
                        <span class="text-muted" style="font-size: 11px;">Ubah stok melalui menu <em>Stock In</em> atau <em>Penyesuaian</em>.</span>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Batas Minimum Stok (Alert)</label>
                    <input type="number" name="min_stock" class="form-control" min="0" value="<?= $product['min_stock'] ?>" required>
                    <span class="text-muted" style="font-size: 11px;">Peringatan akan muncul bila stok &le; angka ini.</span>
                </div>
            </div>

            <div class="form-group" style="margin-top: 8px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" value="1" <?= $product['is_active'] ? 'checked' : '' ?>>
                    <span>Produk Aktif (Tampil di kasir POS)</span>
                </label>
            </div>

            <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border-light); display: flex; justify-content: flex-end; gap: 8px;">
                <a href="products.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Produk' ?></button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
