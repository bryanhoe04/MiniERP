<?php
// receipt.php - Tampilan & Cetak Struk Belanja (ESC/POS & Browser Print)
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

$code = trim($_GET['code'] ?? '');
$id = (int)($_GET['id'] ?? 0);

if (!empty($code)) {
    $stmt = $pdo->prepare("SELECT r.*, t.id as trx_id FROM receipts r JOIN transactions t ON t.id = r.transaction_id WHERE r.receipt_code = ?");
    $stmt->execute([$code]);
} elseif ($id > 0) {
    $stmt = $pdo->prepare("SELECT r.*, t.id as trx_id FROM receipts r JOIN transactions t ON t.id = r.transaction_id WHERE t.id = ?");
    $stmt->execute([$id]);
} else {
    die("Parameter struk tidak valid.");
}

$receipt = $stmt->fetch();
if (!$receipt) {
    die("Struk transaksi tidak ditemukan.");
}

// Tambah print count jika dibuka kembali
$pdo->prepare("UPDATE receipts SET print_count = print_count + 1 WHERE id = ?")->execute([$receipt['id']]);

// Ambil detail transaksi
$trxStmt = $pdo->prepare("
    SELECT t.*, u.name as cashier_name
    FROM transactions t
    LEFT JOIN users u ON u.id = t.user_id
    WHERE t.id = ?
");
$trxStmt->execute([$receipt['transaction_id']]);
$trx = $trxStmt->fetch();

// Ambil detail item
$itemStmt = $pdo->prepare("
    SELECT * FROM transaction_items WHERE transaction_id = ? ORDER BY id ASC
");
$itemStmt->execute([$receipt['transaction_id']]);
$items = $itemStmt->fetchAll();

// Setting Toko
$storeName = get_setting($pdo, 'store_name', 'TOKO MAJU JAYA');
$storeAddress = get_setting($pdo, 'store_address', 'Jl. Kebon Jeruk No. 27, Jakarta');
$storePhone = get_setting($pdo, 'store_phone', '021-1234567');
$receiptFooter = get_setting($pdo, 'receipt_footer', 'Terima kasih atas kunjungan Anda');
$taxRate = (float)get_setting($pdo, 'tax_rate', '11');

$taxCalc = calculate_tax_inclusive($trx['total'], $taxRate);
$isReprint = ($receipt['print_count'] > 1);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk <?= htmlspecialchars($receipt['receipt_code']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background-color: #f1f5f9;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }
        .receipt-container {
            width: 80mm;
            max-width: 100%;
            background: #ffffff;
            padding: 16px;
            border: 1px solid #cbd5e1;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 12px;
        }
        .receipt-store-name {
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .receipt-divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .receipt-double-divider {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            height: 3px;
            margin: 8px 0;
        }
        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        .receipt-item-title {
            font-weight: bold;
        }
        .receipt-item-sub {
            display: flex;
            justify-content: space-between;
            padding-left: 8px;
            color: #222;
        }
        .no-print {
            margin-bottom: 16px;
            display: flex;
            gap: 8px;
        }
        @media print {
            body {
                background: none;
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
            .receipt-container {
                border: none;
                box-shadow: none;
                width: 100%;
                padding: 0;
                margin: 0;
            }
            @page {
                size: 80mm auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" class="btn btn-primary">
        Cetak Struk (Dialog Browser)
    </button>
    <button onclick="printWebSerial()" class="btn btn-secondary">
        Cetak Direct Thermal (ESC/POS)
    </button>
    <a href="pos.php" class="btn btn-secondary">Kembali ke POS</a>
</div>

<div class="receipt-container" id="receiptContent">
    <div class="receipt-header">
        <div class="receipt-store-name"><?= htmlspecialchars($storeName) ?></div>
        <div><?= htmlspecialchars($storeAddress) ?></div>
        <div>Telp: <?= htmlspecialchars($storePhone) ?></div>
        <?php if ($isReprint): ?>
            <div style="font-weight: bold; margin-top: 4px; border: 1px solid #000; display: inline-block; padding: 2px 8px;">*** SALINAN ***</div>
        <?php endif; ?>
        <?php if ($trx['status'] === 'VOIDED'): ?>
            <div style="font-weight: bold; margin-top: 4px; background: #000; color: #fff; padding: 2px 8px;">*** TRANSAKSI DIBATALKAN (VOID) ***</div>
        <?php endif; ?>
    </div>

    <div class="receipt-double-divider"></div>

    <div class="receipt-row">
        <span>No. Struk</span>
        <span><?= htmlspecialchars($receipt['receipt_code']) ?></span>
    </div>
    <div class="receipt-row">
        <span>Tanggal</span>
        <span><?= format_tanggal($trx['created_at'], true) ?></span>
    </div>
    <div class="receipt-row">
        <span>Kasir</span>
        <span><?= htmlspecialchars($trx['cashier_name'] ?? 'Kasir') ?></span>
    </div>

    <div class="receipt-divider"></div>

    <!-- Daftar Item -->
    <div style="margin-bottom: 6px;">
        <?php foreach ($items as $it): ?>
            <div class="receipt-item-title"><?= htmlspecialchars($it['product_name_snapshot']) ?></div>
            <div class="receipt-item-sub">
                <span><?= $it['qty'] ?> x <?= number_format($it['unit_price'], 0, ',', '.') ?></span>
                <span><?= number_format($it['line_total'], 0, ',', '.') ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="receipt-divider"></div>

    <div class="receipt-row">
        <span>Subtotal</span>
        <span><?= number_format($trx['subtotal'], 0, ',', '.') ?></span>
    </div>

    <?php if ($trx['discount'] > 0): ?>
    <div class="receipt-row">
        <span>Diskon</span>
        <span>-<?= number_format($trx['discount'], 0, ',', '.') ?></span>
    </div>
    <?php endif; ?>

    <div class="receipt-row" style="font-weight: bold; font-size: 13px; margin: 4px 0;">
        <span>TOTAL</span>
        <span><?= number_format($trx['total'], 0, ',', '.') ?></span>
    </div>

    <div class="receipt-row">
        <span><?= htmlspecialchars($trx['payment_method']) ?></span>
        <span><?= number_format($trx['amount_paid'], 0, ',', '.') ?></span>
    </div>

    <div class="receipt-row">
        <span>Kembali</span>
        <span><?= number_format($trx['change'], 0, ',', '.') ?></span>
    </div>

    <div class="receipt-divider"></div>

    <div class="receipt-row" style="font-size: 11px; color: #333;">
        <span>DPP</span>
        <span><?= number_format($taxCalc['dpp'], 0, ',', '.') ?></span>
    </div>
    <div class="receipt-row" style="font-size: 11px; color: #333;">
        <span>PPN <?= (int)$taxRate ?>% (termasuk)</span>
        <span><?= number_format($taxCalc['tax'], 0, ',', '.') ?></span>
    </div>

    <div class="receipt-double-divider"></div>

    <div style="text-align: center; margin-top: 10px;">
        <!-- QR Code Kode Struk menggunakan SVG Canvas Ringan -->
        <div style="font-size: 10px; margin-bottom: 4px; font-weight: bold;">[ KODE TRANSAKSI ]</div>
        <div style="padding: 4px; border: 1px solid #333; display: inline-block; font-weight: bold; letter-spacing: 1px;">
            <?= htmlspecialchars($receipt['receipt_code']) ?>
        </div>
        <div style="margin-top: 8px; font-size: 11px;"><?= htmlspecialchars($receiptFooter) ?></div>
    </div>
</div>

<script>
// ESC/POS Direct Print via Web Serial API (Chrome/Edge - PRD RCP-02a)
async function printWebSerial() {
    if (!('serial' in navigator)) {
        alert('Fitur Web Serial tidak didukung oleh peramban ini. Silakan gunakan tombol "Cetak Struk (Dialog Browser)".');
        window.print();
        return;
    }

    try {
        const port = await navigator.serial.requestPort();
        await port.open({ baudRate: 9600 });
        const writer = port.writable.getWriter();
        const encoder = new TextEncoder();

        // ESC/POS Command: Inisialisasi, Bold, Center, Teks, Cut
        const ESC = '\x1B';
        const GS = '\x1D';

        let escposData = ESC + '@'; // Inisialisasi printer
        escposData += ESC + 'a\x01'; // Center alignment
        escposData += ESC + 'E\x01' + '<?= addslashes($storeName) ?>\n' + ESC + 'E\x00';
        escposData += '<?= addslashes($storeAddress) ?>\n';
        escposData += 'Telp: <?= addslashes($storePhone) ?>\n';
        escposData += '--------------------------------\n';
        escposData += ESC + 'a\x00'; // Left alignment
        escposData += 'No: <?= $receipt['receipt_code'] ?>\n';
        escposData += 'Kasir: <?= addslashes($trx['cashier_name'] ?? 'Kasir') ?>\n';
        escposData += 'Tgl: <?= date('d/m/Y H:i', strtotime($trx['created_at'])) ?>\n';
        escposData += '--------------------------------\n';

        <?php foreach ($items as $it): ?>
            escposData += '<?= addslashes($it['product_name_snapshot']) ?>\n';
            escposData += '  <?= $it['qty'] ?> x <?= number_format($it['unit_price'], 0, ',', '.') ?> = <?= number_format($it['line_total'], 0, ',', '.') ?>\n';
        <?php endforeach; ?>

        escposData += '--------------------------------\n';
        escposData += 'TOTAL   : Rp <?= number_format($trx['total'], 0, ',', '.') ?>\n';
        escposData += 'BAYAR   : Rp <?= number_format($trx['amount_paid'], 0, ',', '.') ?>\n';
        escposData += 'KEMBALI : Rp <?= number_format($trx['change'], 0, ',', '.') ?>\n';
        escposData += '--------------------------------\n';
        escposData += ESC + 'a\x01'; // Center
        escposData += '<?= addslashes($receiptFooter) ?>\n\n\n';
        escposData += GS + 'V\x41\x00'; // Feed & Paper Cut

        await writer.write(encoder.encode(escposData));
        writer.releaseLock();
        await port.close();
        alert('Struk berhasil dikirim ke printer thermal.');
    } catch (err) {
        console.error(err);
        alert('Koneksi printer thermal dibatalkan atau gagal: ' + err.message);
    }
}
</script>
</body>
</html>
