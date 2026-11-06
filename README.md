# MiniERP — Prototype ERP Sederhana (PHP Native & SQLite)

Aplikasi prototype ERP berbasis web untuk usaha retail umum / UMKM yang menyatukan **manajemen inventori**, **kasir penjualan (POS)**, **struk thermal (ESC/POS & dialog browser)**, serta **dashboard & laporan penjualan harian/bulanan**.

Dibangun dengan **PHP Native** dan **SQLite** agar sangat ringan, mandiri, dan dapat langsung dijalankan tanpa perlu menginstal server database tambahan (MySQL / Docker).

---

## 1. Cara Menjalankan Aplikasi

Pastikan PHP versi 8.x sudah terpasang di komputer Anda.

Buka terminal di folder proyek ini (`/Users/bryanhoe/BINUS/ERP Prototype 2`), lalu jalankan:

```bash
php -S localhost:8000
```

Buka peramban (browser) dan akses:
**[http://localhost:8000](http://localhost:8000)**

---

## 2. Akun Masuk (Credentials)

Pada halaman login, Anda dapat langsung mengklik tombol **Akses Demo Cepat** (1-klik) atau memasukkan akun berikut:

| Peran (Role) | Email | Sandi Default | Hak Akses |
|---|---|---|---|
| **Admin / Owner** | `admin@minierp.local` | `password123` | Akses penuh, void transaksi, kelola user & pengaturan |
| **Staf Gudang** | `gudang@minierp.local` | `password123` | Master produk, kategori, stock in, riwayat mutasi stok, laporan |
| **Kasir** | `kasir@minierp.local` | `password123` | Kasir (POS), riwayat transaksi miliknya, cetak struk |

---

## 3. Fitur Utama yang Telah Diimplementasikan

### 1. Dashboard Analitik (`index.php`)
- **Kartu KPI**: Penjualan hari ini, jumlah transaksi hari ini, rata-rata belanja (AOV), omzet bulan ini, dan estimasi laba kotor (omzet − HPP snapshot).
- **Grafik Tren Penjualan Harian**: Line chart interaktif untuk melihat omzet harian sepanjang bulan berjalan.
- **Komposisi Metode Pembayaran**: Doughnut chart transaksi (Tunai, QRIS, Debit, Transfer).
- **Top 5 Produk Terlaris**: Berdasarkan jumlah unit terjual dan total omzet.
- **Widget Peringatan Stok Menipis**: Memberitahu produk yang persediaannya berada di bawah batas minimum (`min_stock`) dengan tombol pintas ke *Stock In*.

### 2. Modul Kasir / POS (`pos.php`)
- **Pencarian Cepat & Filter Kategori**: Cari barang berdasarkan nama produk, SKU, atau scan barcode (Shortcut: `F2`).
- **Validasi Stok Otomatis**: Kasir dicegah memasukkan jumlah melebihi stok yang tersedia.
- **Perhitungan Pajak PPN 11% (Tax-Inclusive)**: Harga jual sudah termasuk PPN. Sistem otomatis menghitung DPP dan porsi PPN 11% yang terkandung (`PPN = round(total * 11 / 111)`).
- **Multi-Metode Pembayaran**: Tunai, QRIS, Kartu Debit, dan Transfer Bank.
- **Kalkulasi Kembalian & Tombol Cepat**: Dilengkapi tombol nominal uang pecahan (10k, 20k, 50k, 100k, Uang Pas).
- **Transaksi Atomik (PDO Transaction)**: Pemotongan stok produk, penyimpanan snapshot item, dan pembuatan nomor struk dieksekusi secara aman tanpa race-condition.
- **Shortcut Keyboard Kasir**: `F2` untuk fokus cari produk, `F9` untuk bayar & selesaikan, `Esc` untuk menutup struk.

### 3. Modul Struk Belanja (`receipt.php`)
- **Format Kode Struk Unik**: `RCP-YYYYMMDD-NNNN` (reset harian, 4 digit urutan).
- **Cetak ke Printer Thermal Kasir**:
  - Dukungan cetak langsung via **Web Serial API** menggunakan perintah mentah **ESC/POS** (align, bold, auto cut kertas).
  - Fallback ke dialog print peramban menggunakan layout CSS `@page` khusus ukuran kertas kasir 58mm / 80mm.
- **Penanda Salinan**: Jika struk dicetak ulang dari riwayat, otomatis diberi cap `*** SALINAN ***`.

### 4. Modul Inventori & Manajemen Stok
- **Daftar Produk (`products.php`)**: Filter status stok (Aman, Menipis, Habis), filter kategori, pencarian SKU/barcode, dan badge peringatan stok.
- **Tambah / Edit Produk (`product-form.php`)**: Atur SKU unik, barcode, satuan, harga beli (HPP), harga jual, dan batas stok minimum.
- **Kategori Produk (`categories.php`)**: CRUD kategori barang.
- **Stock In (`stock-in.php`)**: Input penerimaan barang dari pemasok/supplier untuk menambah stok dan memperbarui harga beli.
- **Penyesuaian Stok Fisik / Opname**: Ubah stok riil dengan kewajiban mengisi alasan perubahan.
- **Riwayat Pergerakan Stok (`stock-movements.php`)**: Audit trail setiap pergerakan stok (`SALE`, `STOCK_IN`, `ADJUSTMENT`, `VOID_RETURN`) lengkap dengan nama petugas dan nomor dokumen referensi.

### 5. Riwayat Transaksi & Pembatalan Void (`transactions.php`)
- Pencarian transaksi berdasarkan kode struk, filter tanggal, kasir, dan metode pembayaran.
- **Fitur Void / Pembatalan**: Hanya dapat dilakukan oleh Admin/Owner dengan alasan wajib. Stok barang otomatis dikembalikan ke sistem persediaan dan dicatat sebagai mutasi `VOID_RETURN`.

### 6. Laporan Penjualan & Export (`reports.php`)
- **Laporan Harian**: Rekapitulasi omzet bersih, jumlah transaksi, total PPN, dan rekap per metode pembayaran untuk rekonsiliasi uang kasir.
- **Laporan Bulanan**: Rekap omzet per hari dalam satu bulan.
- **Export Data**: Unduh laporan transaksi dalam format file **CSV** siap buka di Microsoft Excel.

### 7. Pengaturan Sistem (`settings.php`)
- Pengaturan nama toko, alamat, telepon, pesan catatan kaki struk, tarif PPN (%), dan prefix kode struk.
- Manajemen pengguna (tambah akun staf dan tentukan peran).

---

## 4. Kepatuhan Panduan UI/UX (PRD Bab 7a)
- **Tanpa Emoji**: Seluruh antarmuka, label, notifikasi alert, dan struk bebas dari karakter emoji.
- **Border Radius Minimal**: Menggunakan radius tegas `2px` hingga `4px` (`var(--radius-sm)` / `var(--radius-md)`) untuk tampilan padat ala aplikasi kasir & back-office profesional.
- **Ikon Bersih**: Menggunakan ikon vektor SVG filled yang konsisten tanpa library pihak ketiga yang memperlambat.

---

## 6. Integrasi Database ke Supabase (PostgreSQL Cloud)

MiniERP mendukung peralihan dari SQLite lokal ke **Supabase (PostgreSQL)** secara mudah:

1. Buat proyek baru di [Supabase](https://supabase.com).
2. Salin isi berkas `database/supabase_schema.sql`, buka menu **SQL Editor** di dashboard Supabase Anda, lalu klik **Run**.
3. Buka menu **Project Settings -> Database**, cari bagian **Connection Parameters** (Host, Port, Database, User, Password).
4. Buka berkas `config/env.php` di proyek Anda, ubah:
   ```php
   define('DB_DRIVER', 'supabase');
   define('SUPABASE_HOST', 'db.xxxxxxxxxxxxxxxxxxxx.supabase.co');
   define('SUPABASE_PORT', 5432);
   define('SUPABASE_DB',   'postgres');
   define('SUPABASE_USER', 'postgres');
   define('SUPABASE_PASS', 'KATA_SANDI_DATABASE_ANDA');
   ```
5. Buka `http://localhost:8000/test_connection.php` di browser untuk menguji apakah koneksi Supabase Anda sudah terhubung sempurna.

