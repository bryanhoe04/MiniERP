<?php
// settings.php - Pengaturan Toko, Pajak & Manajemen User
$pageTitle = 'Pengaturan Toko & User';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

requireRole(['admin']);

// Handle Simpan Profil Toko & Pajak
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    set_setting($pdo, 'store_name', trim($_POST['store_name'] ?? 'TOKO MAJU JAYA'));
    set_setting($pdo, 'store_address', trim($_POST['store_address'] ?? ''));
    set_setting($pdo, 'store_phone', trim($_POST['store_phone'] ?? ''));
    set_setting($pdo, 'receipt_footer', trim($_POST['receipt_footer'] ?? 'Terima kasih atas kunjungan Anda'));
    set_setting($pdo, 'tax_rate', (string)(int)($_POST['tax_rate'] ?? 11));
    set_setting($pdo, 'receipt_prefix', strtoupper(trim($_POST['receipt_prefix'] ?? 'RCP')));

    set_flash('success', 'Pengaturan toko dan pajak berhasil diperbarui.');
    header('Location: settings.php');
    exit;
}

// Handle Tambah User Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? 'kasir');

    if (!empty($name) && !empty($email) && !empty($password)) {
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hash, $role]);
            set_flash('success', 'User baru berhasil ditambahkan.');
        } catch (PDOException $e) {
            set_flash('error', 'Gagal menambahkan user: Email sudah digunakan.');
        }
    } else {
        set_flash('error', 'Semua kolom data user wajib diisi.');
    }
    header('Location: settings.php');
    exit;
}

// Handle Hapus User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId === (int)$currentUser['id']) {
        set_flash('error', 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        set_flash('success', 'User berhasil dihapus.');
    }
    header('Location: settings.php');
    exit;
}

// Ambil nilai pengaturan
$storeName = get_setting($pdo, 'store_name', 'TOKO MAJU JAYA');
$storeAddress = get_setting($pdo, 'store_address', 'Jl. Kebon Jeruk No. 27, Jakarta');
$storePhone = get_setting($pdo, 'store_phone', '021-1234567');
$receiptFooter = get_setting($pdo, 'receipt_footer', 'Terima kasih atas kunjungan Anda');
$taxRate = get_setting($pdo, 'tax_rate', '11');
$receiptPrefix = get_setting($pdo, 'receipt_prefix', 'RCP');

// Ambil daftar user
$users = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY id ASC")->fetchAll();
?>

<div class="d-flex justify-between align-center mb-16">
    <div>
        <h2 style="font-size: 16px; font-weight: 700;">Pengaturan Toko & Manajemen Pengguna</h2>
        <p class="text-muted" style="font-size: 12px;">Kelola profil usaha, tarif PPN, format struk, dan hak akses staf.</p>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
    <!-- Pengaturan Toko & Pajak -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Profil Toko & Aturan Pajak</span>
        </div>
        <div class="card-body">
            <form method="POST" action="settings.php">
                <input type="hidden" name="action" value="save_settings">

                <div class="form-group">
                    <label class="form-label">Nama Toko / Usaha</label>
                    <input type="text" name="store_name" class="form-control" value="<?= htmlspecialchars($storeName) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Alamat Usaha</label>
                    <input type="text" name="store_address" class="form-control" value="<?= htmlspecialchars($storeAddress) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Nomor Telepon</label>
                    <input type="text" name="store_phone" class="form-control" value="<?= htmlspecialchars($storePhone) ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Tarif PPN (%) - Inklusif</label>
                        <input type="number" name="tax_rate" class="form-control" value="<?= htmlspecialchars($taxRate) ?>" min="0" max="100" required>
                        <span class="text-muted" style="font-size: 11px;">Default: 11% (terkandung dalam harga jual).</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Prefix Kode Struk</label>
                        <input type="text" name="receipt_prefix" class="form-control" value="<?= htmlspecialchars($receiptPrefix) ?>" required>
                        <span class="text-muted" style="font-size: 11px;">Contoh: RCP -> RCP-YYYYMMDD-0001</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Pesan Catatan Kaki Struk (Footer)</label>
                    <input type="text" name="receipt_footer" class="form-control" value="<?= htmlspecialchars($receiptFooter) ?>">
                </div>

                <div style="margin-top: 16px; text-align: right;">
                    <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Manajemen Pengguna & Peran -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Daftar Pengguna Sistem</span>
            <button class="btn btn-secondary btn-sm" onclick="openModal('modalAddUser')">
                + Tambah User
            </button>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th class="text-center">Peran</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($u['name']) ?></td>
                            <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
                            <td class="text-center">
                                <span class="badge <?= $u['role'] === 'admin' ? 'badge-info' : ($u['role'] === 'gudang' ? 'badge-warning' : 'badge-success') ?>">
                                    <?= htmlspecialchars($u['role']) ?>
                                </span>
                            </td>
                            <td class="text-right">
                                <?php if ($u['id'] !== $currentUser['id']): ?>
                                    <form method="POST" action="settings.php" style="display: inline-block;" onsubmit="return confirm('Hapus user <?= htmlspecialchars(addslashes($u['name'])) ?>?')">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--danger);">Hapus</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 11px;">(Akun Anda)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah User -->
<div id="modalAddUser" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <form method="POST" action="settings.php">
            <input type="hidden" name="action" value="create_user">
            <div class="modal-header">
                <div class="modal-title">Tambah Pengguna Baru</div>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="name" class="form-control" placeholder="Nama petugas" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Alamat Email (Login)</label>
                    <input type="email" name="email" class="form-control" placeholder="staf@minierp.local" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Kata Sandi</label>
                    <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Peran Hak Akses (Role)</label>
                    <select name="role" class="form-control" required>
                        <option value="kasir">Kasir (Hanya akses POS & Struk)</option>
                        <option value="gudang">Staf Gudang (Inventori, Stock In, Laporan)</option>
                        <option value="admin">Admin / Owner (Semua Fitur Termasuk Void & Pengaturan)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Pengguna</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
