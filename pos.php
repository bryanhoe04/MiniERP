<?php
// pos.php - Halaman Kasir / Point of Sale
$pageTitle = 'Kasir Penjualan (POS)';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

requireRole(['admin', 'kasir']);

// Ambil semua produk aktif untuk POS
$stmt = $pdo->query("
    SELECT p.id, p.sku, p.barcode, p.name, p.sell_price, p.stock, p.unit, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.is_active = 1
    ORDER BY p.name ASC
");
$allProducts = $stmt->fetchAll();

// Ambil daftar kategori
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>

<div style="display: grid; grid-template-columns: 1fr 380px; gap: 16px; height: calc(100vh - 90px);">
    <!-- Kolom Kiri: Katalog Produk & Pencarian -->
    <div style="display: flex; flex-direction: column; overflow: hidden;">
        <!-- Bar Pencarian Cepat -->
        <div class="card mb-16" style="margin-bottom: 12px;">
            <div class="card-body" style="padding: 10px;">
                <div style="display: flex; gap: 8px;">
                    <div style="flex: 1; position: relative;">
                        <input type="text" id="posSearch" class="form-control" placeholder="Cari nama barang, ketik SKU, atau scan barcode (F2)..." autofocus>
                    </div>
                    <select id="posCategoryFilter" class="form-control" style="width: 160px;">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Grid Katalog Produk -->
        <div style="flex: 1; overflow-y: auto; padding-right: 4px;">
            <div id="productGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px;">
                <?php foreach ($allProducts as $p): ?>
                    <div class="product-item card" 
                         data-id="<?= $p['id'] ?>"
                         data-name="<?= htmlspecialchars($p['name']) ?>"
                         data-sku="<?= htmlspecialchars($p['sku']) ?>"
                         data-barcode="<?= htmlspecialchars($p['barcode'] ?? '') ?>"
                         data-price="<?= $p['sell_price'] ?>"
                         data-stock="<?= $p['stock'] ?>"
                         data-unit="<?= htmlspecialchars($p['unit']) ?>"
                         data-category="<?= htmlspecialchars($p['category_name'] ?? '') ?>"
                         onclick="addToCart(<?= $p['id'] ?>)"
                         style="margin-bottom: 0; cursor: pointer; transition: transform 0.1s, border-color 0.1s; user-select: none;">
                        <div class="card-body" style="padding: 10px; display: flex; flex-direction: column; height: 100%;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;">
                                <span class="badge badge-secondary" style="font-size: 10px;"><?= htmlspecialchars($p['category_name'] ?: 'Umum') ?></span>
                                <span class="badge <?= $p['stock'] <= 0 ? 'badge-danger' : ($p['stock'] <= 5 ? 'badge-warning' : 'badge-success') ?>" style="font-size: 10px;">
                                    Stok: <?= $p['stock'] ?>
                                </span>
                            </div>
                            <div class="fw-bold" style="font-size: 13px; margin-bottom: 4px; line-height: 1.3;">
                                <?= htmlspecialchars($p['name']) ?>
                            </div>
                            <div class="text-muted" style="font-size: 11px; margin-bottom: 8px;">
                                <?= htmlspecialchars($p['sku']) ?>
                            </div>
                            <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: baseline;">
                                <span class="fw-bold" style="color: var(--primary); font-size: 14px;">
                                    <?= format_rupiah($p['sell_price']) ?>
                                </span>
                                <span class="text-muted" style="font-size: 10px;">/<?= htmlspecialchars($p['unit']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="noProductsFound" style="display: none; padding: 40px; text-align: center; color: var(--text-muted);">
                Produk tidak ditemukan. Periksa kata kunci atau SKU.
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Keranjang & Pembayaran -->
    <div class="card" style="display: flex; flex-direction: column; height: 100%; margin-bottom: 0;">
        <div class="card-header" style="padding: 10px 14px;">
            <span class="card-title">Keranjang Penjualan</span>
            <button class="btn btn-secondary btn-sm" onclick="clearCart()" style="color: var(--danger);">Kosongkan</button>
        </div>

        <!-- Daftar Item Belanja -->
        <div style="flex: 1; overflow-y: auto; padding: 10px;" id="cartList">
            <div id="emptyCartMessage" style="text-align: center; color: #94a3b8; padding: 40px 10px; font-size: 12px;">
                Belum ada barang dipilih.<br>Klik produk di sebelah kiri atau scan barcode.
            </div>
            <div id="cartItemsContainer"></div>
        </div>

        <!-- Ringkasan & Pembayaran -->
        <div style="border-top: 1px solid var(--border-light); padding: 12px; background: #fafafa;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 12px;">
                <span class="text-muted">Subtotal</span>
                <span class="fw-bold" id="cartSubtotal">Rp 0</span>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; font-size: 12px;">
                <span class="text-muted">Potongan Diskon (Rp)</span>
                <input type="number" id="discountInput" class="form-control text-right" style="width: 110px; height: 26px;" min="0" value="0" oninput="renderCart()">
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 11px; color: #64748b;">
                <span>Porsi PPN 11% (Termasuk)</span>
                <span id="cartTax">Rp 0</span>
            </div>

            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; padding-top: 6px; border-top: 1px dashed var(--border-color); font-size: 16px;">
                <span class="fw-bold">TOTAL BAYAR</span>
                <span class="fw-bold" id="cartTotal" style="color: var(--primary);">Rp 0</span>
            </div>

            <!-- Metode Pembayaran -->
            <div class="form-group mb-8">
                <label class="form-label" style="font-size: 11px;">Metode Pembayaran</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 4px;">
                    <button type="button" class="btn btn-sm btn-secondary method-btn active" data-method="TUNAI" onclick="selectMethod('TUNAI')">Tunai</button>
                    <button type="button" class="btn btn-sm btn-secondary method-btn" data-method="QRIS" onclick="selectMethod('QRIS')">QRIS</button>
                    <button type="button" class="btn btn-sm btn-secondary method-btn" data-method="DEBIT" onclick="selectMethod('DEBIT')">Debit</button>
                    <button type="button" class="btn btn-sm btn-secondary method-btn" data-method="TRANSFER" onclick="selectMethod('TRANSFER')">Transfer</button>
                </div>
            </div>

            <!-- Input Uang Diterima & Kembalian (Khusus Tunai) -->
            <div id="cashPaymentSection">
                <div class="form-group mb-8">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 3px;">
                        <label class="form-label" style="margin-bottom: 0; font-size: 11px;">Uang Diterima (Rp)</label>
                        <button type="button" class="btn btn-secondary btn-sm" style="height: 20px; font-size: 10px; padding: 0 4px;" onclick="setExactAmount()">Uang Pas</button>
                    </div>
                    <input type="number" id="cashReceived" class="form-control" style="font-size: 14px; font-weight: bold;" placeholder="0" oninput="calculateChange()">
                </div>

                <!-- Tombol Cepat Nominal Uang -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; margin-bottom: 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" style="font-size: 10px; padding: 2px;" onclick="addCash(10000)">10.000</button>
                    <button type="button" class="btn btn-secondary btn-sm" style="font-size: 10px; padding: 2px;" onclick="addCash(20000)">20.000</button>
                    <button type="button" class="btn btn-secondary btn-sm" style="font-size: 10px; padding: 2px;" onclick="addCash(50000)">50.000</button>
                    <button type="button" class="btn btn-secondary btn-sm" style="font-size: 10px; padding: 2px;" onclick="addCash(100000)">100.000</button>
                </div>

                <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 12px; background: #ffffff; padding: 6px 8px; border: 1px solid var(--border-light); border-radius: var(--radius-sm);">
                    <span class="text-muted">Kembalian:</span>
                    <span class="fw-bold" id="cashChange" style="color: var(--success);">Rp 0</span>
                </div>
            </div>

            <button type="button" id="btnCheckout" class="btn btn-primary btn-lg" style="width: 100%;" onclick="processCheckout()">
                Bayar & Selesaikan (F9)
            </button>
        </div>
    </div>
</div>

<!-- Modal Struk Berhasil -->
<div id="modalReceiptSuccess" class="modal-overlay" style="display: none;">
    <div class="modal-content" style="max-width: 440px;">
        <div class="modal-header">
            <div class="modal-title">Transaksi Berhasil Disimpan</div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeReceiptModal()">Tutup</button>
        </div>
        <div class="modal-body" style="text-align: center; padding: 20px;">
            <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 4px;">Nomor Struk Transaksi</div>
            <div id="successReceiptCode" style="font-size: 20px; font-weight: 700; color: var(--primary); letter-spacing: 1px; margin-bottom: 16px;">
                RCP-XXXXXXXX-XXXX
            </div>
            
            <div style="background: #f8fafc; border: 1px solid var(--border-light); padding: 12px; margin-bottom: 20px; border-radius: var(--radius-sm); font-size: 13px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span>Total Pembelian</span>
                    <span class="fw-bold" id="successTotal">Rp 0</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span>Uang Diterima</span>
                    <span id="successPaid">Rp 0</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-weight: bold; color: var(--success);">
                    <span>Kembalian</span>
                    <span id="successChange">Rp 0</span>
                </div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 8px;">
                <a id="btnOpenReceipt" href="#" target="_blank" class="btn btn-primary" style="width: 100%;">
                    Buka & Cetak Struk Belanja
                </a>
                <button type="button" class="btn btn-secondary" onclick="closeReceiptModal()">
                    Mulai Transaksi Baru (Esc)
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Data Keranjang POS
let cart = [];
let selectedPaymentMethod = 'TUNAI';

// Katalog data di JavaScript
const productsCatalog = <?= json_encode($allProducts) ?>;

function addToCart(productId) {
    productId = Number(productId);
    const product = productsCatalog.find(p => Number(p.id) === productId);
    if (!product) return;

    if (product.stock <= 0) {
        alert(`Stok produk "${product.name}" habis.`);
        return;
    }

    const existing = cart.find(item => Number(item.id) === productId);
    if (existing) {
        if (existing.qty + 1 > product.stock) {
            alert(`Jumlah melebihi stok yang tersedia (${product.stock} ${product.unit}).`);
            return;
        }
        existing.qty += 1;
    } else {
        cart.push({
            id: Number(product.id),
            name: product.name,
            sku: product.sku,
            price: Number(product.sell_price),
            stock: Number(product.stock),
            unit: product.unit,
            qty: 1
        });
    }

    renderCart();
}

function updateQty(productId, newQty) {
    productId = Number(productId);
    const item = cart.find(i => Number(i.id) === productId);
    if (!item) return;

    newQty = parseInt(newQty, 10);
    if (isNaN(newQty) || newQty <= 0) {
        removeFromCart(productId);
        return;
    }

    if (newQty > item.stock) {
        alert(`Stok hanya tersisa ${item.stock} ${item.unit}.`);
        item.qty = item.stock;
    } else {
        item.qty = newQty;
    }

    renderCart();
}

function removeFromCart(productId) {
    productId = Number(productId);
    cart = cart.filter(i => Number(i.id) !== productId);
    renderCart();
}

function clearCart() {
    if (cart.length === 0) return;
    if (confirm('Kosongkan semua barang di keranjang?')) {
        cart = [];
        renderCart();
    }
}

function selectMethod(method) {
    selectedPaymentMethod = method;
    document.querySelectorAll('.method-btn').forEach(btn => {
        if (btn.getAttribute('data-method') === method) {
            btn.classList.add('active');
            btn.classList.remove('btn-secondary');
            btn.classList.add('btn-primary');
        } else {
            btn.classList.remove('active');
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-secondary');
        }
    });

    const cashSection = document.getElementById('cashPaymentSection');
    if (method === 'TUNAI') {
        cashSection.style.display = 'block';
    } else {
        cashSection.style.display = 'none';
    }
    calculateChange();
}

function setExactAmount() {
    const total = getCartNetTotal();
    document.getElementById('cashReceived').value = total;
    calculateChange();
}

function addCash(amount) {
    const current = Number(document.getElementById('cashReceived').value) || 0;
    document.getElementById('cashReceived').value = current + amount;
    calculateChange();
}

function getCartSubtotal() {
    return cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
}

function getCartNetTotal() {
    const subtotal = getCartSubtotal();
    const discount = Number(document.getElementById('discountInput').value) || 0;
    return Math.max(0, subtotal - discount);
}

function calculateChange() {
    const total = getCartNetTotal();
    const received = Number(document.getElementById('cashReceived').value) || 0;
    const change = Math.max(0, received - total);
    document.getElementById('cashChange').innerText = formatRupiahJs(change);
}

function renderCart() {
    const emptyMsg = document.getElementById('emptyCartMessage');
    const itemsContainer = document.getElementById('cartItemsContainer');
    const subtotal = getCartSubtotal();
    const discount = Number(document.getElementById('discountInput').value) || 0;
    const total = Math.max(0, subtotal - discount);

    // Hitung porsi PPN 11% Tax-inclusive
    const taxRate = 11;
    const taxAmount = Math.round((total * taxRate) / (100 + taxRate));

    const subtotalEl = document.getElementById('cartSubtotal');
    const taxEl = document.getElementById('cartTax');
    const totalEl = document.getElementById('cartTotal');

    if (subtotalEl) subtotalEl.innerText = formatRupiahJs(subtotal);
    if (taxEl) taxEl.innerText = formatRupiahJs(taxAmount);
    if (totalEl) totalEl.innerText = formatRupiahJs(total);

    if (cart.length === 0) {
        if (itemsContainer) itemsContainer.innerHTML = '';
        if (emptyMsg) emptyMsg.style.display = 'block';
        const cashRec = document.getElementById('cashReceived');
        if (cashRec) cashRec.value = '';
        calculateChange();
        return;
    }

    if (emptyMsg) emptyMsg.style.display = 'none';
    let html = '';

    cart.forEach(item => {
        const itemLineTotal = item.price * item.qty;
        html += `
            <div style="background: #ffffff; border: 1px solid var(--border-light); border-radius: var(--radius-sm); padding: 8px 10px; margin-bottom: 6px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;">
                    <div>
                        <div class="fw-bold" style="font-size: 12px;">${escapeHtml(item.name)}</div>
                        <div class="text-muted" style="font-size: 10px;">${formatRupiahJs(item.price)} / ${escapeHtml(item.unit)}</div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" style="color: var(--danger); height: 20px; padding: 0 4px; font-size: 10px;" onclick="removeFromCart(${item.id})">✕</button>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 4px;">
                        <button type="button" class="btn btn-secondary btn-sm" style="height: 24px; width: 24px; padding: 0; font-weight: bold;" onclick="updateQty(${item.id}, ${item.qty - 1})">-</button>
                        <input type="number" class="form-control text-center" style="width: 48px; height: 24px; padding: 0 4px; font-size: 12px;" value="${item.qty}" min="1" max="${item.stock}" onchange="updateQty(${item.id}, this.value)">
                        <button type="button" class="btn btn-secondary btn-sm" style="height: 24px; width: 24px; padding: 0; font-weight: bold;" onclick="updateQty(${item.id}, ${item.qty + 1})">+</button>
                    </div>
                    <div class="fw-bold" style="font-size: 13px;">${formatRupiahJs(itemLineTotal)}</div>
                </div>
            </div>
        `;
    });

    if (itemsContainer) itemsContainer.innerHTML = html;
    calculateChange();
}

async function processCheckout() {
    if (cart.length === 0) {
        alert('Keranjang belanja masih kosong.');
        return;
    }

    const total = getCartNetTotal();
    const discount = Number(document.getElementById('discountInput').value) || 0;
    let amountPaid = Number(document.getElementById('cashReceived').value) || 0;

    if (selectedPaymentMethod === 'TUNAI' && amountPaid < total) {
        alert(`Uang tunai yang diterima (${formatRupiahJs(amountPaid)}) masih kurang dari total belanja (${formatRupiahJs(total)}).`);
        document.getElementById('cashReceived').focus();
        return;
    }

    if (selectedPaymentMethod !== 'TUNAI') {
        amountPaid = total;
    }

    const btn = document.getElementById('btnCheckout');
    btn.disabled = true;
    btn.innerText = 'Memproses...';

    try {
        const response = await fetch('pos-process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                items: cart,
                payment_method: selectedPaymentMethod,
                discount_nominal: discount,
                amount_paid: amountPaid
            })
        });

        const result = await response.json();

        if (result.success) {
            // Tampilkan modal struk sukses
            document.getElementById('successReceiptCode').innerText = result.receipt_code;
            document.getElementById('successTotal').innerText = formatRupiahJs(result.total);
            document.getElementById('successPaid').innerText = formatRupiahJs(result.amount_paid);
            document.getElementById('successChange').innerText = formatRupiahJs(result.change);
            document.getElementById('btnOpenReceipt').href = `receipt.php?code=${encodeURIComponent(result.receipt_code)}`;

            openModal('modalReceiptSuccess');

            // Update stok lokal agar katalog produk selalu sinkron
            cart.forEach(cItem => {
                const prod = productsCatalog.find(p => Number(p.id) === Number(cItem.id));
                if (prod) {
                    prod.stock = Math.max(0, prod.stock - cItem.qty);
                    const card = document.querySelector(`.product-item[data-id="${prod.id}"]`);
                    if (card) {
                        card.setAttribute('data-stock', prod.stock);
                        const badge = card.querySelector('.badge:last-of-type');
                        if (badge) {
                            badge.innerText = 'Stok: ' + prod.stock;
                            if (prod.stock <= 0) {
                                badge.className = 'badge badge-danger';
                            } else if (prod.stock <= 5) {
                                badge.className = 'badge badge-warning';
                            } else {
                                badge.className = 'badge badge-success';
                            }
                        }
                    }
                }
            });

            // Reset keranjang
            cart = [];
            document.getElementById('discountInput').value = 0;
            document.getElementById('cashReceived').value = '';
            renderCart();

            // Opsional: Buka struk otomatis di tab baru
            // window.open(`receipt.php?code=${encodeURIComponent(result.receipt_code)}`, '_blank');
        } else {
            alert('Gagal memproses transaksi: ' + result.message);
        }
    } catch (e) {
        alert('Terjadi kesalahan jaringan: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerText = 'Bayar & Selesaikan (F9)';
    }
}

function closeReceiptModal() {
    closeModal('modalReceiptSuccess');
    document.getElementById('posSearch').focus();
}

// Pencarian Cepat Produk di POS
document.getElementById('posSearch').addEventListener('input', function(e) {
    filterProducts();
});

document.getElementById('posCategoryFilter').addEventListener('change', function(e) {
    filterProducts();
});

function filterProducts() {
    const q = document.getElementById('posSearch').value.toLowerCase().trim();
    const cat = document.getElementById('posCategoryFilter').value;
    const cards = document.querySelectorAll('.product-item');
    let visibleCount = 0;

    cards.forEach(card => {
        const name = card.getAttribute('data-name').toLowerCase();
        const sku = card.getAttribute('data-sku').toLowerCase();
        const barcode = card.getAttribute('data-barcode').toLowerCase();
        const category = card.getAttribute('data-category');

        const matchQuery = !q || name.includes(q) || sku.includes(q) || barcode.includes(q);
        const matchCat = !cat || category === cat;

        if (matchQuery && matchCat) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    document.getElementById('noProductsFound').style.display = visibleCount === 0 ? 'block' : 'none';
}

// Shortcut Keyboard: F2 (Cari), F9 (Bayar), Esc (Batal/Tutup Modal)
window.addEventListener('keydown', function(e) {
    if (e.key === 'F2') {
        e.preventDefault();
        document.getElementById('posSearch').focus();
    } else if (e.key === 'F9') {
        e.preventDefault();
        processCheckout();
    } else if (e.key === 'Escape') {
        closeReceiptModal();
    }
});

function formatRupiahJs(num) {
    return 'Rp ' + Number(num).toLocaleString('id-ID');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.innerText = text;
    return div.innerHTML;
}

// Inisialisasi awal
selectMethod('TUNAI');
renderCart();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
