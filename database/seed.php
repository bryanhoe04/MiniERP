<?php
// database/seed.php - Seeder Data Sederhana (Hanya 3 Produk Saja)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

echo "Membersihkan dan menyiapkan 3 produk sederhana...\n";

$pdo->beginTransaction();

// 1. Bersihkan semua riwayat transaksi & mutasi
$pdo->exec("DELETE FROM transaction_items");
$pdo->exec("DELETE FROM receipts");
$pdo->exec("DELETE FROM transactions");
$pdo->exec("DELETE FROM stock_in_items");
$pdo->exec("DELETE FROM stock_in");
$pdo->exec("DELETE FROM stock_movements");
$pdo->exec("DELETE FROM products");
$pdo->exec("DELETE FROM categories");

// 2. Kategori Sederhana
$categories = ['Sembako & Kebutuhan Dapur', 'Makanan Ringan & Snack'];
$catMap = [];
foreach ($categories as $cat) {
    $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->execute([$cat]);
    $catMap[$cat] = (int)$pdo->lastInsertId();
}

// 3. Hanya 3 Produk Saja
$productsData = [
    ['SKU-001', '8991234001', 'Beras Ramos Super 5kg', 'Sembako & Kebutuhan Dapur', 68000, 78000, 25, 5, 'sak'],
    ['SKU-002', '8991234002', 'Minyak Goreng Sania 2L', 'Sembako & Kebutuhan Dapur', 31000, 36500, 40, 10, 'pouch'],
    ['SKU-009', '8991234009', 'Indomie Goreng Spesial 85g', 'Makanan Ringan & Snack', 2800, 3500, 50, 10, 'bks']
];

$prodStmt = $pdo->prepare("
    INSERT INTO products (
        sku, barcode, name, category_id,
        cost_price, sell_price, stock, min_stock, unit, is_active
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
");

$adminId = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn() ?: 1;

$movStmt = $pdo->prepare("
    INSERT INTO stock_movements (product_id, type, qty_change, stock_after, reference, user_id, note)
    VALUES (?, 'ADJUSTMENT', ?, ?, 'STOK AWAL', ?, 'Inisialisasi stok awal')
");

foreach ($productsData as $pd) {
    $catId = $catMap[$pd[3]] ?? null;
    $prodStmt->execute([
        $pd[0], $pd[1], $pd[2], $catId,
        $pd[4], $pd[5], $pd[6], $pd[7], $pd[8]
    ]);
    $pId = (int)$pdo->lastInsertId();

    $movStmt->execute([$pId, $pd[6], $pd[6], $adminId]);
}

$pdo->commit();
echo "Selesai! Hanya 3 produk yang tersimpan dan seluruh riwayat transaksi bersih.\n";
