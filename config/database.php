<?php
// config/database.php - Koneksi Database (Mendukung Supabase PostgreSQL & SQLite)

// Muat konfigurasi environment
if (file_exists(__DIR__ . '/env.php')) {
    require_once __DIR__ . '/env.php';
}

$driver = defined('DB_DRIVER') ? DB_DRIVER : 'sqlite';

try {
    if ($driver === 'supabase' || $driver === 'pgsql') {
        // Koneksi ke Supabase (PostgreSQL Cloud)
        $host = defined('SUPABASE_HOST') ? SUPABASE_HOST : '127.0.0.1';
        $port = defined('SUPABASE_PORT') ? SUPABASE_PORT : 5432;
        $db   = defined('SUPABASE_DB') ? SUPABASE_DB : 'postgres';
        $user = defined('SUPABASE_USER') ? SUPABASE_USER : 'postgres';
        $pass = defined('SUPABASE_PASS') ? SUPABASE_PASS : '';

        $dsn = "pgsql:host={$host};port={$port};dbname={$db};sslmode=require";
        
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } else {
        // Koneksi ke SQLite Bawaan (Local File)
        $dbDir = __DIR__ . '/../database';
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        $dbPath = $dbDir . '/database.sqlite';
        $isFirstRun = !file_exists($dbPath);

        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON;');

        if ($isFirstRun) {
            initializeDatabase($pdo);
        }
    }
} catch (PDOException $e) {
    die("Koneksi Database Gagal: " . $e->getMessage());
}

function initializeDatabase(PDO $pdo) {
    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('admin', 'gudang', 'kasir')),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS suppliers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        phone TEXT,
        address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sku TEXT UNIQUE NOT NULL,
        barcode TEXT,
        name TEXT NOT NULL,
        category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
        cost_price INTEGER NOT NULL DEFAULT 0,
        sell_price INTEGER NOT NULL DEFAULT 0,
        stock INTEGER NOT NULL DEFAULT 0,
        min_stock INTEGER NOT NULL DEFAULT 5,
        unit TEXT NOT NULL DEFAULT 'pcs',
        image TEXT,
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS stock_in (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        supplier_id INTEGER REFERENCES suppliers(id) ON DELETE SET NULL,
        user_id INTEGER REFERENCES users(id),
        note TEXT,
        received_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS stock_in_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        stock_in_id INTEGER REFERENCES stock_in(id) ON DELETE CASCADE,
        product_id INTEGER REFERENCES products(id),
        qty INTEGER NOT NULL,
        cost_price INTEGER NOT NULL
    );

    CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER REFERENCES users(id),
        subtotal INTEGER NOT NULL,
        discount INTEGER NOT NULL DEFAULT 0,
        tax INTEGER NOT NULL DEFAULT 0,
        total INTEGER NOT NULL,
        payment_method TEXT NOT NULL,
        amount_paid INTEGER NOT NULL,
        change INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT 'COMPLETED' CHECK(status IN ('COMPLETED', 'VOIDED', 'REFUNDED')),
        void_reason TEXT,
        void_by INTEGER REFERENCES users(id),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS transaction_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        transaction_id INTEGER REFERENCES transactions(id) ON DELETE CASCADE,
        product_id INTEGER REFERENCES products(id),
        product_name_snapshot TEXT NOT NULL,
        qty INTEGER NOT NULL,
        unit_price INTEGER NOT NULL,
        cost_price_snapshot INTEGER NOT NULL DEFAULT 0,
        discount INTEGER NOT NULL DEFAULT 0,
        line_total INTEGER NOT NULL
    );

    CREATE TABLE IF NOT EXISTS receipts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        receipt_code TEXT UNIQUE NOT NULL,
        transaction_id INTEGER UNIQUE REFERENCES transactions(id) ON DELETE CASCADE,
        print_count INTEGER NOT NULL DEFAULT 0,
        issued_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS stock_movements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id INTEGER REFERENCES products(id),
        type TEXT NOT NULL CHECK(type IN ('SALE', 'STOCK_IN', 'ADJUSTMENT', 'VOID_RETURN')),
        qty_change INTEGER NOT NULL,
        stock_after INTEGER NOT NULL,
        reference TEXT,
        user_id INTEGER REFERENCES users(id),
        note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    );
    ";

    $pdo->exec($sql);

    // Pengaturan default toko & pajak
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
    $stmt->execute(['store_name', 'TOKO MAJU JAYA']);
    $stmt->execute(['store_address', 'Jl. Kebon Jeruk No. 27, Jakarta']);
    $stmt->execute(['store_phone', '021-1234567']);
    $stmt->execute(['receipt_footer', 'Terima kasih atas kunjungan Anda']);
    $stmt->execute(['tax_rate', '11']);
    $stmt->execute(['receipt_prefix', 'RCP']);

    // Akun default (Password: password123)
    $passwordHash = password_hash('password123', PASSWORD_BCRYPT);
    $userStmt = $pdo->prepare("INSERT OR IGNORE INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
    $userStmt->execute(['Administrator', 'admin@minierp.local', $passwordHash, 'admin']);
    $userStmt->execute(['Staf Gudang', 'gudang@minierp.local', $passwordHash, 'gudang']);
    $userStmt->execute(['Kasir Utama', 'kasir@minierp.local', $passwordHash, 'kasir']);
}
