<?php
// includes/sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
$user = getCurrentUser();
$role = $user['role'] ?? 'kasir';
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <span>MINIERP</span>
        <span class="sidebar-badge">V1.0</span>
    </div>
    
    <nav class="sidebar-nav">
        <!-- Main -->
        <div class="nav-section-title">Utama</div>
        <a href="index.php" class="nav-item <?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <svg class="icon" viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
            <span>Dashboard</span>
        </a>

        <?php if ($role === 'admin' || $role === 'kasir'): ?>
        <a href="pos.php" class="nav-item <?= $currentPage === 'pos.php' ? 'active' : '' ?>">
            <svg class="icon" viewBox="0 0 24 24"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49c.08-.14.12-.31.12-.48 0-.55-.45-1-1-1H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
            <span>Kasir (POS)</span>
        </a>
        <?php endif; ?>

        <!-- Inventori -->
        <?php if ($role === 'admin' || $role === 'gudang'): ?>
        <div class="nav-section-title">Inventori</div>
        <a href="products.php" class="nav-item <?= in_array($currentPage, ['products.php', 'product-form.php']) ? 'active' : '' ?>">
            <svg class="icon" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-9 16H6v-2h5v2zm0-4H6v-2h5v2zm0-4H6V8h5v2zm7 8h-5v-2h5v2zm0-4h-5v-2h5v2zm0-4h-5V8h5v2z"/></svg>
            <span>Daftar Produk</span>
        </a>
        <a href="categories.php" class="nav-item <?= $currentPage === 'categories.php' ? 'active' : '' ?>">
            <svg class="icon" viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
            <span>Kategori</span>
        </a>
        <a href="stock-in.php" class="nav-item <?= $currentPage === 'stock-in.php' ? 'active' : '' ?>">
            <svg class="icon" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
            <span>Stock In (Masuk)</span>
        </a>
        <a href="stock-movements.php" class="nav-item <?= $currentPage === 'stock-movements.php' ? 'active' : '' ?>">
            <svg class="icon" viewBox="0 0 24 24"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>
            <span>Riwayat Stok</span>
        </a>
        <?php endif; ?>

        <!-- Penjualan & Laporan -->
        <div class="nav-section-title">Penjualan</div>
        <a href="transactions.php" class="nav-item <?= in_array($currentPage, ['transactions.php', 'transaction-detail.php']) ? 'active' : '' ?>">
            <svg class="icon" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
            <span>Riwayat Transaksi</span>
        </a>

        <?php if ($role === 'admin' || $role === 'gudang'): ?>
        <a href="reports.php" class="nav-item <?= $currentPage === 'reports.php' ? 'active' : '' ?>">
            <svg class="icon" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
            <span>Laporan Penjualan</span>
        </a>
        <?php endif; ?>

        <!-- Sistem -->
        <?php if ($role === 'admin'): ?>
        <div class="nav-section-title">Sistem</div>
        <a href="settings.php" class="nav-item <?= $currentPage === 'settings.php' ? 'active' : '' ?>">
            <svg class="icon" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
            <span>Pengaturan & User</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div style="font-weight: 600; color: #f1f5f9; margin-bottom: 2px;"><?= htmlspecialchars($user['name'] ?? 'Pengguna') ?></div>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span class="role-pill" style="border: none; background: #1e293b; color: #94a3b8;"><?= htmlspecialchars($role) ?></span>
            <a href="logout.php" style="color: #ef4444; text-decoration: none; font-size: 11px; font-weight: 600;">Keluar</a>
        </div>
    </div>
</aside>

<div class="main-content">
    <header class="topbar">
        <div class="topbar-title"><?= htmlspecialchars($pageTitle) ?></div>
        <div class="topbar-user">
            <span class="text-muted" style="font-size: 12px;"><?= date('l, d/m/Y') ?></span>
            <span class="role-pill"><?= htmlspecialchars($user['name'] ?? '') ?> (<?= htmlspecialchars($role) ?>)</span>
            <a href="logout.php" class="btn btn-secondary btn-sm" style="color: var(--danger);">Keluar</a>
        </div>
    </header>

    <main class="content-body">
    <?php
    $flash = get_flash();
    if ($flash):
    ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>
