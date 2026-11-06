<?php
// includes/functions.php - Helper Functions

// Set zona waktu default ke WIB (Asia/Jakarta) sesuai PRD Bab 6
date_default_timezone_set('Asia/Jakarta');

function format_rupiah($nominal): string {
    return 'Rp ' . number_format((float)$nominal, 0, ',', '.');
}

function format_tanggal($datetime, bool $withTime = false): string {
    if (!$datetime) return '-';
    $timestamp = strtotime($datetime);
    if ($withTime) {
        return date('d/m/Y H:i', $timestamp);
    }
    return date('d/m/Y', $timestamp);
}

/**
 * Perhitungan PPN Tax-Inclusive (PRD Bab 6 Aturan 6)
 * Harga sudah termasuk PPN.
 * total = subtotal - diskon
 * PPN = round(total * tarif / (100 + tarif))
 * DPP = total - PPN
 */
function calculate_tax_inclusive(float $total, float $taxRate = 11): array {
    if ($total <= 0) {
        return ['dpp' => 0, 'tax' => 0];
    }
    $tax = round(($total * $taxRate) / (100 + $taxRate));
    $dpp = $total - $tax;
    return [
        'dpp' => (int)$dpp,
        'tax' => (int)$tax
    ];
}

/**
 * Generate kode struk unik RCP-YYYYMMDD-NNNN (PRD Bab 3.3 & Aturan 2)
 * Reset setiap hari, zero-padded 4 digit.
 */
function generate_receipt_code(PDO $pdo, string $prefix = 'RCP'): string {
    $today = date('Ymd');
    $pattern = $prefix . '-' . $today . '-%';

    // Cari nomor urut terbesar hari ini
    $stmt = $pdo->prepare("
        SELECT receipt_code FROM receipts 
        WHERE receipt_code LIKE ? 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$pattern]);
    $lastCode = $stmt->fetchColumn();

    $nextNumber = 1;
    if ($lastCode) {
        $parts = explode('-', $lastCode);
        if (count($parts) === 3) {
            $nextNumber = (int)$parts[2] + 1;
        }
    }

    $sequence = str_pad((string)$nextNumber, 4, '0', STR_PAD_LEFT);
    return "{$prefix}-{$today}-{$sequence}";
}

function get_setting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return ($val !== false && $val !== null) ? $val : $default;
}

function set_setting(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value");
    $stmt->execute([$key, $value]);
}

function sanitize(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function set_flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
