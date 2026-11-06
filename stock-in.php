<?php
// stock-in.php - Penerimaan Barang dari Supplier (Stock In)
$pageTitle = 'Penerimaan Barang (Stock In)';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

requireRole(['admin', 'gudang']);

// Handle Simpan Stock In
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_stock_in') {
    $supplierName = trim($_POST['supplier_name'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $items = $_POST['items'] ?? [];

    if (empty($items)) {
        set_flash('error', 'Pilih minimal satu produk untuk penerimaan barang.');
        header('Location: stock-in.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Cari atau buat data supplier sederhana
        $supplierId = null;
        if (!empty($supplierName)) {
            $supStmt = $pdo->prepare("SELECT id FROM suppliers WHERE name = ? LIMIT 1");
            $supStmt->execute([$supplierName]);
            $supplierId = $supStmt->fetchColumn();

            if (!$supplierId) {
                $insSup = $pdo->prepare("INSERT INTO suppliers (name) VALUES (?)");
                $insSup->execute([$supplierName]);
                $supplierId = $pdo->lastInsertId();
            }
        }

        // Simpan header stock in
        $headerStmt = $pdo->prepare("
            INSERT INTO stock_in (supplier_id, user_id, note)
            VALUES (?, ?, ?)
        ");
        $headerStmt->execute([$supplierId, $currentUser['id'], $note]);
        $stockInId = $pdo->lastInsertId();

        $itemStmt = $pdo->prepare("
            INSERT INTO stock_in_items (stock_in_id, product_id, qty, cost_price)
            VALUES (?, ?, ?, ?)
        ");

        $updateStockStmt = $pdo->prepare("
            UPDATE products SET stock = stock + ?, cost_price = ? WHERE id = ?
        ");

        $movementStmt = $pdo->prepare("
            INSERT INTO stock_movements (product_id, type, qty_change, stock_after, reference, user_id, note)
            VALUES (?, 'STOCK_IN', ?, ?, ?, ?, ?)
        ");

        $totalItemsReceived = 0;

        foreach ($items as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            $qty = (int)($item['qty'] ?? 0);
            $costPrice = (int)($item['cost_price'] ?? 0);

            if ($productId > 0 && $qty > 0) {
                // Ambil stok sekarang
                $curStmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
                $curStmt->execute([$productId]);
                $currentStock = (int)$curStmt->fetchColumn();
                $newStock = $currentStock + $qty;

                // 1. Simpan detail item
                $itemStmt->execute([$stockInId, $productId, $qty, $costPrice]);

                // 2. Update stok & harga beli produk
                $updateStockStmt->execute([$qty, $costPrice, $productId]);

                // 3. Catat stock movement
                $movementStmt->execute([
                    $productId,
                    $qty,
                    $newStock,
                    "IN-#{$stockInId}",
                    $currentUser['id'],
                    $note ?: 'Penerimaan supplier'
                ]);

                $totalItemsReceived += $qty;
            }
        }

        $pdo->commit();
        set_flash('success', "Stock In berhasil disimpan. Total {$totalItemsReceived} unit barang ditambahkan ke persediaan.");
        header('Location: stock-in.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('error', 'Gagal memproses Stock In: ' . $e->getMessage());
        header('Location: stock-in.php');
        exit;
    }
}

// Handle Penyesuaian Stok (Stock Adjustment - INV-06)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $adjustedQty = (int)($_POST['adjusted_qty'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if ($productId <= 0 || empty($reason)) {
        set_flash('error', 'Produk dan alasan penyesuaian wajib diisi.');
        header('Location: stock-in.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $oldStock = (int)$stmt->fetchColumn();

        // adjustedQty adalah stok riil baru hasil opname
        $diff = $adjustedQty - $oldStock;

        if ($diff !== 0) {
            $upd = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
            $upd->execute([$adjustedQty, $productId]);

            $mov = $pdo->prepare("
                INSERT INTO stock_movements (product_id, type, qty_change, stock_after, reference, user_id, note)
                VALUES (?, 'ADJUSTMENT', ?, ?, 'OPNAME', ?, ?)
            ");
            $mov->execute([$productId, $diff, $adjustedQty, $currentUser['id'], $reason]);
        }

        $pdo->commit();
        set_flash('success', "Penyesuaian stok berhasil disimpan. Stok diperbarui menjadi {$adjustedQty}.");
        header('Location: stock-in.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('error', 'Gagal menyesuaikan stok: ' . $e->getMessage());
        header('Location: stock-in.php');
        exit;
    }
}

// Ambil daftar produk aktif
$products = $pdo->query("SELECT id, sku, name, stock, cost_price, unit FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Riwayat 10 Stock In Terakhir
$recentStockIn = $pdo->query("
    SELECT si.*, s.name as supplier_name, u.name as user_name,
           COUNT(sii.id) as item_count, SUM(sii.qty) as total_qty
    FROM stock_in si
    LEFT JOIN suppliers s ON s.id = si.supplier_id
    LEFT JOIN users u ON u.id = si.user_id
    LEFT JOIN stock_in_items sii ON sii.stock_in_id = si.id
    GROUP BY si.id
    ORDER BY si.id DESC LIMIT 10
")->fetchAll();
?>

<div class="d-flex justify-between align-center mb-16">
    <div>
        <h2 style="font-size: 16px; font-weight: 700;">Penerimaan Barang (Stock In)</h2>
        <p class="text-muted" style="font-size: 12px;">Catat stok masuk dari pemasok/supplier atau lakukan penyesuaian stok opname.</p>
    </div>
    <div class="d-flex gap-8">
        <button class="btn btn-secondary" onclick="openModal('modalAdjustment')">
            Penyesuaian Stok (Opname)
        </button>
    </div>
</div>

<div class="card mb-16">
    <div class="card-header">
        <span class="card-title">Form Input Barang Masuk</span>
    </div>
    <div class="card-body">
        <form method="POST" action="stock-in.php" id="formStockIn">
            <input type="hidden" name="action" value="save_stock_in">
            
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px; margin-bottom: 16px;">
                <div class="form-group">
                    <label class="form-label">Nama Supplier / Pemasok</label>
                    <input type="text" name="supplier_name" class="form-control" placeholder="Contoh: PT Sumber Pangan, Toko Makmur">
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan / No. Surat Jalan</label>
                    <input type="text" name="note" class="form-control" placeholder="Contoh: SJ-2026/10/012 - Restock bulanan">
                </div>
            </div>

            <div class="table-responsive mb-16">
                <table class="table" id="tableItems">
                    <thead>
                        <tr>
                            <th style="min-width: 250px;">Pilih Produk</th>
                            <th style="width: 130px;" class="text-center">Stok Saat Ini</th>
                            <th style="width: 140px;" class="text-center">Jumlah Masuk</th>
                            <th style="width: 180px;" class="text-right">Harga Beli Baru (Rp)</th>
                            <th style="width: 60px;" class="text-center">Hapus</th>
                        </tr>
                    </thead>
                    <tbody id="itemsContainer">
                        <!-- Baris item pertama -->
                        <tr>
                            <td>
                                <select name="items[0][product_id]" class="form-control product-select" required onchange="updateProductMeta(this, 0)">
                                    <option value="">-- Pilih Produk --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock'] ?>" data-cost="<?= $p['cost_price'] ?>" data-unit="<?= htmlspecialchars($p['unit']) ?>">
                                            <?= htmlspecialchars($p['sku']) ?> - <?= htmlspecialchars($p['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="text-center text-muted" id="current-stock-0">-</td>
                            <td>
                                <input type="number" name="items[0][qty]" class="form-control text-center" min="1" value="1" required>
                            </td>
                            <td>
                                <input type="number" name="items[0][cost_price]" class="form-control text-right" id="cost-price-0" min="0" value="0" required>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="removeRow(this)" style="color: var(--danger);">X</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-between align-center">
                <button type="button" class="btn btn-secondary btn-sm" onclick="addRow()">
                    + Tambah Baris Produk
                </button>
                <button type="submit" class="btn btn-primary">
                    Simpan Penerimaan Barang
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Riwayat Penerimaan Terakhir -->
<div class="card">
    <div class="card-header">
        <span class="card-title">10 Riwayat Penerimaan Barang Terakhir</span>
        <a href="stock-movements.php" class="btn btn-secondary btn-sm">Lihat Semua Mutasi Stok</a>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 80px;">No. Dokumen</th>
                    <th style="width: 160px;">Tanggal Masuk</th>
                    <th>Supplier</th>
                    <th>Catatan</th>
                    <th class="text-center">Jumlah Item</th>
                    <th class="text-center">Total Unit</th>
                    <th>Penerima</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentStockIn)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted" style="padding: 20px;">Belum ada riwayat penerimaan barang.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentStockIn as $row): ?>
                        <tr>
                            <td class="fw-bold">#IN-<?= $row['id'] ?></td>
                            <td><?= format_tanggal($row['received_at'], true) ?></td>
                            <td><?= htmlspecialchars($row['supplier_name'] ?: 'Umum / Non-Supplier') ?></td>
                            <td class="text-muted"><?= htmlspecialchars($row['note'] ?: '-') ?></td>
                            <td class="text-center"><span class="badge badge-secondary"><?= $row['item_count'] ?> item</span></td>
                            <td class="text-center fw-bold"><?= $row['total_qty'] ?> unit</td>
                            <td><?= htmlspecialchars($row['user_name'] ?? 'Sistem') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Penyesuaian Stok (INV-06) -->
<div id="modalAdjustment" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <form method="POST" action="stock-in.php">
            <input type="hidden" name="action" value="adjust_stock">
            <div class="modal-header">
                <div class="modal-title">Penyesuaian Stok Fisik (Stock Opname)</div>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Pilih Produk *</label>
                    <select name="product_id" class="form-control" required onchange="showAdjustmentCurrentStock(this)">
                        <option value="">-- Pilih Produk --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock'] ?>" data-unit="<?= htmlspecialchars($p['unit']) ?>">
                                <?= htmlspecialchars($p['sku']) ?> - <?= htmlspecialchars($p['name']) ?> (Stok Sistem: <?= $p['stock'] ?> <?= htmlspecialchars($p['unit']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Jumlah Stok Fisik Riil Sekarang *</label>
                    <input type="number" name="adjusted_qty" id="adjusted_qty" class="form-control" min="0" required>
                    <span class="text-muted" style="font-size: 11px;">Stok di sistem akan langsung disesuaikan dengan nilai ini.</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Alasan Penyesuaian (Wajib diisi) *</label>
                    <textarea name="reason" class="form-control" placeholder="Contoh: Selisih stock opname, barang rusak, barang kedaluwarsa" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Penyesuaian</button>
            </div>
        </form>
    </div>
</div>

<script>
let rowIndex = 1;
const productOptions = `<?php foreach ($products as $p): ?>
    <option value="<?= $p['id'] ?>" data-stock="<?= $p['stock'] ?>" data-cost="<?= $p['cost_price'] ?>" data-unit="<?= htmlspecialchars($p['unit']) ?>">
        <?= htmlspecialchars(addslashes($p['sku'])) ?> - <?= htmlspecialchars(addslashes($p['name'])) ?>
    </option>
<?php endforeach; ?>`;

function updateProductMeta(select, idx) {
    const selected = select.options[select.selectedIndex];
    const stock = selected.getAttribute('data-stock') || '-';
    const unit = selected.getAttribute('data-unit') || '';
    const cost = selected.getAttribute('data-cost') || '0';

    const stockElem = document.getElementById(`current-stock-${idx}`);
    const costElem = document.getElementById(`cost-price-${idx}`);

    if (stockElem) stockElem.innerText = stock !== '-' ? `${stock} ${unit}` : '-';
    if (costElem) costElem.value = cost;
}

function addRow() {
    const tbody = document.getElementById('itemsContainer');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select name="items[${rowIndex}][product_id]" class="form-control product-select" required onchange="updateProductMeta(this, ${rowIndex})">
                <option value="">-- Pilih Produk --</option>
                ${productOptions}
            </select>
        </td>
        <td class="text-center text-muted" id="current-stock-${rowIndex}">-</td>
        <td>
            <input type="number" name="items[${rowIndex}][qty]" class="form-control text-center" min="1" value="1" required>
        </td>
        <td>
            <input type="number" name="items[${rowIndex}][cost_price]" class="form-control text-right" id="cost-price-${rowIndex}" min="0" value="0" required>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-secondary btn-sm" onclick="removeRow(this)" style="color: var(--danger);">X</button>
        </td>
    `;
    tbody.appendChild(tr);
    rowIndex++;
}

function removeRow(btn) {
    const tbody = document.getElementById('itemsContainer');
    if (tbody.querySelectorAll('tr').length > 1) {
        btn.closest('tr').remove();
    } else {
        alert('Minimal satu baris produk harus tersedia.');
    }
}

function showAdjustmentCurrentStock(select) {
    const selected = select.options[select.selectedIndex];
    const stock = selected.getAttribute('data-stock');
    if (stock !== null) {
        document.getElementById('adjusted_qty').value = stock;
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
