<?php
// categories.php - Manajemen Kategori Produk
$pageTitle = 'Kategori Produk';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

requireRole(['admin', 'gudang']);

// Handle Tambah / Edit / Hapus Kategori
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                $stmt->execute([$name]);
                set_flash('success', 'Kategori baru berhasil ditambahkan.');
            } catch (PDOException $e) {
                set_flash('error', 'Gagal menambahkan kategori: Nama kategori sudah digunakan.');
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($id > 0 && !empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
                $stmt->execute([$name, $id]);
                set_flash('success', 'Kategori berhasil diperbarui.');
            } catch (PDOException $e) {
                set_flash('error', 'Gagal memperbarui: Nama kategori sudah ada.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            set_flash('success', 'Kategori berhasil dihapus.');
        }
    }
    header('Location: categories.php');
    exit;
}

// Ambil data kategori beserta jumlah produknya
$query = "
    SELECT c.*, COUNT(p.id) as total_products
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id
    ORDER BY c.name ASC
";
$categories = $pdo->query($query)->fetchAll();
?>

<div class="d-flex justify-between align-center mb-16">
    <div>
        <h2 style="font-size: 16px; font-weight: 700;">Kategori Produk</h2>
        <p class="text-muted" style="font-size: 12px;">Kelola pengelompokan produk retail.</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('modalAdd')">
        Tambah Kategori
    </button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Nama Kategori</th>
                    <th style="width: 150px;" class="text-center">Jumlah Produk</th>
                    <th style="width: 180px;">Dibuat Pada</th>
                    <th style="width: 140px;" class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted" style="padding: 24px;">Belum ada data kategori. Klik 'Tambah Kategori' untuk membuat baru.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $idx => $cat): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($cat['name']) ?></td>
                            <td class="text-center">
                                <span class="badge badge-secondary"><?= $cat['total_products'] ?> item</span>
                            </td>
                            <td class="text-muted"><?= format_tanggal($cat['created_at'], true) ?></td>
                            <td class="text-right">
                                <button class="btn btn-secondary btn-sm" onclick="editCategory(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>')">
                                    Edit
                                </button>
                                <form method="POST" action="categories.php" style="display: inline-block;" onsubmit="return confirm('Yakin ingin menghapus kategori ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm" style="color: var(--danger);">
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Kategori -->
<div id="modalAdd" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <form method="POST" action="categories.php">
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <div class="modal-title">Tambah Kategori Baru</div>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nama Kategori</label>
                    <input type="text" name="name" class="form-control" placeholder="Contoh: Makanan Ringan, Minuman, Sembako" required autofocus>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Kategori</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Kategori -->
<div id="modalEdit" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <form method="POST" action="categories.php">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header">
                <div class="modal-title">Edit Kategori</div>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Tutup</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nama Kategori</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCategory(id, name) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    openModal('modalEdit');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
