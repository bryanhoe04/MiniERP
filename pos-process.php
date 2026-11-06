<?php
// pos-process.php - Endpoint Proses Checkout POS Atomik
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Sesi login telah berakhir.']);
    exit;
}

$currentUser = getCurrentUser();
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Data transaksi tidak valid.']);
    exit;
}

$items = $input['items'] ?? [];
$paymentMethod = trim($input['payment_method'] ?? 'TUNAI');
$discountNominal = (int)($input['discount_nominal'] ?? 0);
$amountPaid = (int)($input['amount_paid'] ?? 0);

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Keranjang belanja masih kosong.']);
    exit;
}

try {
    // Memulai Transaksi Database Atomik (PRD Bab 3.2 TRX-06 & Bab 7)
    $pdo->beginTransaction();

    $taxRate = (float)get_setting($pdo, 'tax_rate', '11');
    $receiptPrefix = get_setting($pdo, 'receipt_prefix', 'RCP');

    $calculatedSubtotal = 0;
    $preparedItems = [];

    // Validasi stok & ambil snapshot data produk terkini
    $checkStmt = $pdo->prepare("SELECT id, name, cost_price, sell_price, stock, unit, is_active FROM products WHERE id = ?");

    foreach ($items as $item) {
        $pId = (int)($item['id'] ?? 0);
        $qty = (int)($item['qty'] ?? 0);

        if ($pId <= 0 || $qty <= 0) {
            throw new Exception('Data item belanja tidak valid.');
        }

        $checkStmt->execute([$pId]);
        $prod = $checkStmt->fetch();

        if (!$prod || !$prod['is_active']) {
            throw new Exception("Produk #{$pId} tidak ditemukan atau sudah dinonaktifkan.");
        }

        // Validasi stok tidak boleh negatif (PRD Bab 6 Aturan 1)
        if ($prod['stock'] < $qty) {
            throw new Exception("Stok untuk produk '{$prod['name']}' tidak mencukupi (Sisa stok: {$prod['stock']} {$prod['unit']}).");
        }

        $unitPrice = (int)$prod['sell_price'];
        $lineTotal = $unitPrice * $qty;
        $calculatedSubtotal += $lineTotal;

        $preparedItems[] = [
            'product_id' => $prod['id'],
            'product_name' => $prod['name'],
            'cost_price' => (int)$prod['cost_price'],
            'unit_price' => $unitPrice,
            'qty' => $qty,
            'line_total' => $lineTotal,
            'current_stock' => (int)$prod['stock'],
            'unit' => $prod['unit']
        ];
    }

    if ($discountNominal > $calculatedSubtotal) {
        $discountNominal = $calculatedSubtotal;
    }

    $total = $calculatedSubtotal - $discountNominal;

    // Validasi nominal pembayaran jika tunai
    if ($paymentMethod === 'TUNAI' && $amountPaid < $total) {
        throw new Exception("Nominal uang yang diterima (" . format_rupiah($amountPaid) . ") kurang dari total belanja (" . format_rupiah($total) . ").");
    }

    if ($paymentMethod !== 'TUNAI') {
        $amountPaid = $total; // Non-tunai langsung lunas
    }

    $change = $amountPaid - $total;

    // Perhitungan DPP & PPN 11% Tax-Inclusive (PRD Bab 6 Aturan 6)
    $taxCalc = calculate_tax_inclusive($total, $taxRate);
    $tax = $taxCalc['tax'];

    // 1. Simpan Header Transaksi
    $trxStmt = $pdo->prepare("
        INSERT INTO transactions (
            user_id, subtotal, discount, tax, total, 
            payment_method, amount_paid, change, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'COMPLETED')
    ");
    $trxStmt->execute([
        $currentUser['id'],
        $calculatedSubtotal,
        $discountNominal,
        $tax,
        $total,
        $paymentMethod,
        $amountPaid,
        $change
    ]);
    $transactionId = (int)$pdo->lastInsertId();

    // 2. Generate Kode Struk Unik RCP-YYYYMMDD-NNNN
    $receiptCode = generate_receipt_code($pdo, $receiptPrefix);

    $receiptStmt = $pdo->prepare("
        INSERT INTO receipts (receipt_code, transaction_id, print_count)
        VALUES (?, ?, 1)
    ");
    $receiptStmt->execute([$receiptCode, $transactionId]);

    // 3. Simpan Item Transaksi + Kurangi Stok + Catat Stock Movement
    $itemInsertStmt = $pdo->prepare("
        INSERT INTO transaction_items (
            transaction_id, product_id, product_name_snapshot,
            qty, unit_price, cost_price_snapshot, discount, line_total
        ) VALUES (?, ?, ?, ?, ?, ?, 0, ?)
    ");

    $updateStockStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

    $movementStmt = $pdo->prepare("
        INSERT INTO stock_movements (
            product_id, type, qty_change, stock_after, reference, user_id, note
        ) VALUES (?, 'SALE', ?, ?, ?, ?, ?)
    ");

    foreach ($preparedItems as $pItem) {
        // Snapshot disimpan ke transaction_items
        $itemInsertStmt->execute([
            $transactionId,
            $pItem['product_id'],
            $pItem['product_name'],
            $pItem['qty'],
            $pItem['unit_price'],
            $pItem['cost_price'],
            $pItem['line_total']
        ]);

        // Potong stok produk
        $updateStockStmt->execute([$pItem['qty'], $pItem['product_id']]);

        // Catat mutasi stok
        $stockAfter = $pItem['current_stock'] - $pItem['qty'];
        $movementStmt->execute([
            $pItem['product_id'],
            -$pItem['qty'],
            $stockAfter,
            $receiptCode,
            $currentUser['id'],
            "Penjualan kasir #{$transactionId}"
        ]);
    }

    // Commit transaksi atomik
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Transaksi berhasil diproses.',
        'transaction_id' => $transactionId,
        'receipt_code' => $receiptCode,
        'total' => $total,
        'amount_paid' => $amountPaid,
        'change' => $change
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
