<?php
// transactions.php - Riwayat Transaksi & Fitur Void/Refund
$pageTitle = 'Riwayat Transaksi';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

requireRole(['admin', 'kasir']);

// Handle Pembatalan Transaksi (Void) - Hanya Admin (PRD TRX-09 & Aturan 4)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'void_transaction') {
    if ($currentUser['role'] !== 'admin') {
        set_flash('error', 'Hanya Admin/Manager yang memiliki hak untuk membatalkan (void) transaksi.');
        header('Location: transactions.php');
        exit;
    }

    $trxId = (int)($_POST['transaction_id'] ?? 0);
    $voidReason = trim($_POST['void_reason'] ?? '');

    if ($trxId <= 0 || empty($voidReason)) {
        set_flash('error', 'ID transaksi dan alasan pembatalan (void) wajib diisi.');
        header('Location: transactions.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
        $stmt->execute([$trxId]);
        $trx = $stmt->fetch();

        if (!$trx) {
            throw new Exception('Transaksi tidak ditemukan.');
        }

        if ($trx['status'] === 'VOIDED') {
            throw new Exception('Transaksi ini sudah berstatus VOID sebelumnya.');
        }

        // Ambil kode struk
        $rcpStmt = $pdo->prepare("SELECT receipt_code FROM receipts WHERE transaction_id = ?");
        $rcpStmt->execute([$trxId]);
        $receiptCode = $rcpStmt->fetchColumn() ?: "TRX-{$trxId}";

        // Ambil item transaksi untuk mengembalikan stok
        $itemsStmt = $pdo->prepare("SELECT * FROM transaction_items WHERE transaction_id = ?");
        $itemsStmt->execute([$trxId]);
        $items = $itemsStmt->fetchAll();

        $returnStockStmt = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        $movementStmt = $pdo->prepare("
            INSERT INTO stock_movements (product_id, type, qty_change, stock_after, reference, user_id, note)
            VALUES (?, 'VOID_RETURN', ?, ?, ?, ?, ?)
        ");

        foreach ($items as $it) {
            if ($it['product_id']) {
                // Ambil stok saat ini
                $sStmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
                $sStmt->execute([$it['product_id']]);
                $currStock = (int)$sStmt->fetchColumn();
                $newStock = $currStock + (int)$it['qty'];

                // 1. Kembalikan stok
                $returnStockStmt->execute([(int)$it['qty'], $it['product_id']]);

                // 2. Catat mutasi VOID_RETURN
                $movementStmt->execute([
                    $it['product_id'],
                    (int)$it['qty'],
                    $newStock,
                    $receiptCode,
                    $currentUser['id'],
                    "Void transaksi: " . $voidReason
                ]);
            }
        }

        // Update status transaksi menjadi VOIDED
        $updateTrx = $pdo->prepare("
            UPDATE transactions 
            SET status = 'VOIDED', void_reason = ?, void_by = ?
            WHERE id = ?
        ");
        $updateTrx->execute([$voidReason, $currentUser['id'], $trxId]);

        $pdo->commit();
        set_flash('success', "Transaksi {$receiptCode} berhasil di-void. Stok barang telah dikembalikan ke sistem.");
        header('Location: transactions.php');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        set_flash('error', 'Gagal membatalkan transaksi: ' . $e->getMessage());
        header('Location: transactions.php');
        exit;
    }
}

// Filter Pencarian
$receiptSearch = trim($_GET['search'] ?? '');
$filterDate = trim($_GET['date'] ?? '');
$filterMethod = trim($_GET['payment_method'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$where = ["1=1"];
$params = [];

// Jika kasir biasa, hanya boleh lihat transaksi hari ini atau miliknya jika diatur
if ($currentUser['role'] === 'kasir') {
    $where[] = "t.user_id = ?";
    $params[] = $currentUser['id'];
}

if (!empty($receiptSearch)) {
    $where[] = "(r.receipt_code LIKE ? OR u.name LIKE ?)";
    $like = "%{$receiptSearch}%";
    $params[] = $like;
    $params[] = $like;
}

if (!empty($filterDate)) {
    $where[] = "DATE(t.created_at) = ?";
    $params[] = $filterDate;
}

if (!empty($filterMethod)) {
    $where[] = "t.payment_method = ?";
    $params[] = $filterMethod;
}

if (!empty($filterStatus)) {
    $where[] = "t.status = ?";
    $params[] = $filterStatus;
}

$whereClause = implode(" AND ", $where);

$sql = "
    SELECT t.*, r.receipt_code, r.print_count, u.name as cashier_name,
           (SELECT COUNT(*) FROM transaction_items ti WHERE ti.transaction_id = t.id) as total_items
    FROM transactions t
    LEFT JOIN receipts r ON r.transaction_id = t.id
    LEFT JOIN users u ON u.id = t.user_id
    WHERE {$whereClause}
    ORDER BY t.id DESC
    LIMIT 150
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();
?>

<div class="d-flex justify-between align-center mb-16">
    <div>
        <h2 style="font-size: 16px; font-weight: 700;">Riwayat Transaksi Penjualan</h2>
        <p class="text-muted" style="font-size: 12px;">Daftar transaksi, pencarian kode struk, cetak ulang, dan audit void.</p>
    </div>
    <a href="pos.php" class="btn btn-primary">
        Buka Kasir POS
    </a>
</div>

<!-- Filter Bar -->
<div class="card mb-16">
    <div class="card-body" style="padding: 12px;">
        <form method="GET" action="transactions.php" class="form-inline">
            <div style="flex: 2; min-width: 200px;">
                <input type="text" name="search" class="form-control" placeholder="Cari nomor struk (RCP-...) / kasir" value="<?= htmlspecialchars($receiptSearch) ?>">
            </div>
            <div style="flex: 1; min-width: 130px;">
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($filterDate) ?>">
            </div>
            <div style="flex: 1; min-width: 130px;">
                <select name="payment_method" class="form-control">
                    <option value="">Semua Metode</option>
                    <option value="TUNAI" <?= $filterMethod === 'TUNAI' ? 'selected' : '' ?>>TUNAI</option>
                    <option value="QRIS" <?= $filterMethod === 'QRIS' ? 'selected' : '' ?>>QRIS</option>
                    <option value="DEBIT" <?= $filterMethod === 'DEBIT' ? 'selected' : '' ?>>DEBIT</option>
                    <option value="TRANSFER" <?= $filterMethod === 'TRANSFER' ? 'selected' : '' ?>>TRANSFER</option>
                </select>
            </div>
            <div style="flex: 1; min-width: 130px;">
                <select name="status" class="form-control">
                    <option value="">Semua Status</option>
                    <option value="COMPLETED" <?= $filterStatus === 'COMPLETED' ? 'selected' : '' ?>>COMPLETED</option>
                    <option value="VOIDED" <?= $filterStatus === 'VOIDED' ? 'selected' : '' ?>>VOIDED</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">Filter</button>
            <?php if (!empty($receiptSearch) || !empty($filterDate) || !empty($filterMethod) || !empty($filterStatus)): ?>
                <a href="transactions.php" class="btn btn-secondary" style="color: var(--text-muted);">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 170px;">No. Struk</th>
                    <th style="width: 140px;">Waktu & Tanggal</th>
                    <th>Kasir</th>
                    <th class="text-center" style="width: 100px;">Item</th>
                    <th style="width: 100px;">Metode</th>
                    <th class="text-right" style="width: 140px;">Total Penjualan</th>
                    <th class="text-center" style="width: 100px;">Status</th>
                    <th class="text-right" style="width: 170px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted" style="padding: 24px;">Tidak ada transaksi ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $t): ?>
                        <tr style="<?= $t['status'] === 'VOIDED' ? 'opacity: 0.65; background: #fff5f5;' : '' ?>">
                            <td>
                                <a href="receipt.php?code=<?= urlencode($t['receipt_code']) ?>" target="_blank" class="fw-bold" style="color: var(--primary); text-decoration: none;">
                                    <?= htmlspecialchars($t['receipt_code']) ?>
                                </a>
                                <?php if ($t['print_count'] > 1): ?>
                                    <div class="text-muted" style="font-size: 10px;">Dicetak <?= $t['print_count'] ?>x</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= format_tanggal($t['created_at'], true) ?></td>
                            <td><?= htmlspecialchars($t['cashier_name'] ?? 'Kasir') ?></td>
                            <td class="text-center"><?= $t['total_items'] ?> item</td>
                            <td>
                                <span class="badge badge-secondary"><?= htmlspecialchars($t['payment_method']) ?></span>
                            </td>
                            <td class="text-right fw-bold">
                                <?= format_rupiah($t['total']) ?>
                            </td>
                            <td class="text-center">
                                <?php if ($t['status'] === 'COMPLETED'): ?>
                                    <span class="badge badge-success">COMPLETED</span>
                                <?php else: ?>
                                    <span class="badge badge-danger" title="<?= htmlspecialchars($t['void_reason'] ?? '') ?>">VOIDED</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <a href="receipt.php?code=<?= urlencode($t['receipt_code']) ?>" target="_blank" class="btn btn-secondary btn-sm">
                                    Struk
                                </a>
                                <?php if ($t['status'] === 'COMPLETED' && $currentUser['role'] === 'admin'): ?>
                                    <button class="btn btn-secondary btn-sm" style="color: var(--danger);" onclick="openVoidModal(<?= $t['id'] ?>, '<?= htmlspecialchars(addslashes($t['receipt_code'])) ?>', '<?= format_rupiah($t['total']) ?>')">
                                        Void
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Konfirmasi Void (TRX-09) -->
<div id="modalVoid" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <form method="POST" action="transactions.php">
            <input type="hidden" name="action" value="void_transaction">
            <input type="hidden" name="transaction_id" id="void_trx_id">

            <div class="modal-header">
                <div class="modal-title" style="color: var(--danger);">Batalkan Transaksi (Void)</div>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning mb-16">
                    Peringatan: Transaksi yang di-void tidak dapat dikembalikan. Stok barang akan otomatis dikembalikan ke sistem inventori.
                </div>

                <div style="background: #f8fafc; border: 1px solid var(--border-light); padding: 12px; margin-bottom: 16px; border-radius: var(--radius-sm);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span class="text-muted">Nomor Struk:</span>
                        <strong id="voidReceiptCode">-</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span class="text-muted">Total Nilai:</span>
                        <strong id="voidTotalAmount">-</strong>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Alasan Pembatalan (Wajib diisi) *</label>
                    <textarea name="void_reason" class="form-control" placeholder="Contoh: Pelanggan salah bayar, retur barang rusak, salah input item kasir" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger">Konfirmasi Pembatalan (Void)</button>
            </div>
        </form>
    </div>
</div>

<script>
function openVoidModal(id, code, total) {
    document.getElementById('void_trx_id').value = id;
    document.getElementById('voidReceiptCode').innerText = code;
    document.getElementById('voidTotalAmount').innerText = total;
    openModal('modalVoid');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
