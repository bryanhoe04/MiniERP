-- ========================================================
-- MiniERP Schema for Supabase (PostgreSQL)
-- Jalankan skrip ini di SQL Editor dashboard Supabase Anda.
-- ========================================================

-- 1. Fungsi Kompatibilitas strftime untuk PostgreSQL
CREATE OR REPLACE FUNCTION strftime(format text, ts timestamp)
RETURNS text AS $$
BEGIN
    IF format = '%Y-%m' THEN
        RETURN to_char(ts, 'YYYY-MM');
    ELSIF format = '%d' THEN
        RETURN to_char(ts, 'DD');
    ELSIF format = '%Y' THEN
        RETURN to_char(ts, 'YYYY');
    ELSE
        RETURN to_char(ts, 'YYYY-MM-DD');
    END IF;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE FUNCTION strftime(format text, ts timestamp with time zone)
RETURNS text AS $$
BEGIN
    IF format = '%Y-%m' THEN
        RETURN to_char(ts, 'YYYY-MM');
    ELSIF format = '%d' THEN
        RETURN to_char(ts, 'DD');
    ELSIF format = '%Y' THEN
        RETURN to_char(ts, 'YYYY');
    ELSE
        RETURN to_char(ts, 'YYYY-MM-DD');
    END IF;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

-- 2. Buat Tabel-tabel MiniERP

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL CHECK(role IN ('admin', 'gudang', 'kasir')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS suppliers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    address TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS products (
    id SERIAL PRIMARY KEY,
    sku VARCHAR(100) UNIQUE NOT NULL,
    barcode VARCHAR(100),
    name VARCHAR(255) NOT NULL,
    category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    cost_price BIGINT NOT NULL DEFAULT 0,
    sell_price BIGINT NOT NULL DEFAULT 0,
    stock INTEGER NOT NULL DEFAULT 0,
    min_stock INTEGER NOT NULL DEFAULT 5,
    unit VARCHAR(50) NOT NULL DEFAULT 'pcs',
    image TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS stock_in (
    id SERIAL PRIMARY KEY,
    supplier_id INTEGER REFERENCES suppliers(id) ON DELETE SET NULL,
    user_id INTEGER REFERENCES users(id),
    note TEXT,
    received_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS stock_in_items (
    id SERIAL PRIMARY KEY,
    stock_in_id INTEGER REFERENCES stock_in(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products(id),
    qty INTEGER NOT NULL,
    cost_price BIGINT NOT NULL
);

CREATE TABLE IF NOT EXISTS transactions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    subtotal BIGINT NOT NULL,
    discount BIGINT NOT NULL DEFAULT 0,
    tax BIGINT NOT NULL DEFAULT 0,
    total BIGINT NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    amount_paid BIGINT NOT NULL,
    change BIGINT NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'COMPLETED' CHECK(status IN ('COMPLETED', 'VOIDED', 'REFUNDED')),
    void_reason TEXT,
    void_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS transaction_items (
    id SERIAL PRIMARY KEY,
    transaction_id INTEGER REFERENCES transactions(id) ON DELETE CASCADE,
    product_id INTEGER REFERENCES products(id),
    product_name_snapshot VARCHAR(255) NOT NULL,
    qty INTEGER NOT NULL,
    unit_price BIGINT NOT NULL,
    cost_price_snapshot BIGINT NOT NULL DEFAULT 0,
    discount BIGINT NOT NULL DEFAULT 0,
    line_total BIGINT NOT NULL
);

CREATE TABLE IF NOT EXISTS receipts (
    id SERIAL PRIMARY KEY,
    receipt_code VARCHAR(100) UNIQUE NOT NULL,
    transaction_id INTEGER UNIQUE REFERENCES transactions(id) ON DELETE CASCADE,
    print_count INTEGER NOT NULL DEFAULT 0,
    issued_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS stock_movements (
    id SERIAL PRIMARY KEY,
    product_id INTEGER REFERENCES products(id),
    type VARCHAR(50) NOT NULL CHECK(type IN ('SALE', 'STOCK_IN', 'ADJUSTMENT', 'VOID_RETURN')),
    qty_change INTEGER NOT NULL,
    stock_after INTEGER NOT NULL,
    reference VARCHAR(100),
    user_id INTEGER REFERENCES users(id),
    note TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
    key VARCHAR(100) PRIMARY KEY,
    value TEXT
);

-- 3. Inisialisasi Data Awal (Settings, Akun Pengguna & 3 Produk Saja)

-- Pengaturan Default
INSERT INTO settings (key, value) VALUES 
('store_name', 'TOKO MAJU JAYA'),
('store_address', 'Jl. Kebon Jeruk No. 27, Jakarta'),
('store_phone', '021-1234567'),
('receipt_footer', 'Terima kasih atas kunjungan Anda'),
('tax_rate', '11'),
('receipt_prefix', 'RCP')
ON CONFLICT (key) DO NOTHING;

-- Akun Default (Password: password123)
-- Hash bcrypt '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeF1mE0u6Z5g2wTph7mffQd.f04VqK0bK'
INSERT INTO users (name, email, password_hash, role) VALUES
('Administrator', 'admin@minierp.local', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeF1mE0u6Z5g2wTph7mffQd.f04VqK0bK', 'admin'),
('Staf Gudang', 'gudang@minierp.local', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeF1mE0u6Z5g2wTph7mffQd.f04VqK0bK', 'gudang'),
('Kasir Utama', 'kasir@minierp.local', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeF1mE0u6Z5g2wTph7mffQd.f04VqK0bK', 'kasir')
ON CONFLICT (email) DO NOTHING;

-- Kategori Awal
INSERT INTO categories (name) VALUES 
('Sembako & Kebutuhan Dapur'),
('Makanan Ringan & Snack')
ON CONFLICT (name) DO NOTHING;

-- 3 Produk Awal
INSERT INTO products (sku, barcode, name, category_id, cost_price, sell_price, stock, min_stock, unit, is_active)
SELECT 'SKU-001', '8991234001', 'Beras Ramos Super 5kg', c.id, 68000, 78000, 25, 5, 'sak', 1
FROM categories c WHERE c.name = 'Sembako & Kebutuhan Dapur'
ON CONFLICT (sku) DO NOTHING;

INSERT INTO products (sku, barcode, name, category_id, cost_price, sell_price, stock, min_stock, unit, is_active)
SELECT 'SKU-002', '8991234002', 'Minyak Goreng Sania 2L', c.id, 31000, 36500, 40, 10, 'pouch', 1
FROM categories c WHERE c.name = 'Sembako & Kebutuhan Dapur'
ON CONFLICT (sku) DO NOTHING;

INSERT INTO products (sku, barcode, name, category_id, cost_price, sell_price, stock, min_stock, unit, is_active)
SELECT 'SKU-009', '8991234009', 'Indomie Goreng Spesial 85g', c.id, 2800, 3500, 50, 10, 'bks', 1
FROM categories c WHERE c.name = 'Makanan Ringan & Snack'
ON CONFLICT (sku) DO NOTHING;
