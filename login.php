<?php
// login.php - Halaman Masuk
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Cek quick login untuk demo prototype
    if (isset($_POST['quick_role'])) {
        $quickRole = $_POST['quick_role'];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? LIMIT 1");
        $stmt->execute([$quickRole]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: index.php');
            exit;
        }
    }

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'Email atau password yang Anda masukkan salah.';
        }
    }
}

$storeName = get_setting($pdo, 'store_name', 'TOKO MAJU JAYA');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - MiniERP</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="background-color: #0f172a; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="max-width: 420px; width: 100%; border: 1px solid #1e293b; background: #ffffff;">
        <div class="card-header" style="background: #ffffff; padding: 18px; border-bottom: 1px solid #e2e8f0; text-align: center; display: block;">
            <div style="font-size: 11px; font-weight: 700; color: #64748b; letter-spacing: 1px; text-transform: uppercase;">Prototype Sistem ERP</div>
            <h1 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-top: 4px;"><?= htmlspecialchars($storeName) ?></h1>
        </div>

        <div class="card-body" style="padding: 20px;">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" style="margin-bottom: 16px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label class="form-label">Alamat Email</label>
                    <input type="email" name="email" class="form-control" placeholder="admin@minierp.local" required autofocus>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label">Kata Sandi</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; height: 38px;">
                    Masuk ke Sistem
                </button>
            </form>

            <div style="margin: 20px 0 14px 0; border-top: 1px solid #e2e8f0; position: relative; text-align: center;">
                <span style="position: absolute; top: -9px; left: 50%; transform: translateX(-50%); background: #ffffff; padding: 0 8px; font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Akses Demo Cepat</span>
            </div>

            <form method="POST" action="login.php" style="display: flex; gap: 6px; flex-direction: column;">
                <button type="submit" name="quick_role" value="admin" class="btn btn-secondary btn-sm" style="width: 100%; text-align: left; justify-content: space-between;">
                    <span>Masuk sebagai <strong>Admin / Owner</strong></span>
                    <span class="badge badge-info">Admin</span>
                </button>
                <button type="submit" name="quick_role" value="gudang" class="btn btn-secondary btn-sm" style="width: 100%; text-align: left; justify-content: space-between;">
                    <span>Masuk sebagai <strong>Staf Gudang</strong></span>
                    <span class="badge badge-warning">Gudang</span>
                </button>
                <button type="submit" name="quick_role" value="kasir" class="btn btn-secondary btn-sm" style="width: 100%; text-align: left; justify-content: space-between;">
                    <span>Masuk sebagai <strong>Kasir</strong></span>
                    <span class="badge badge-success">Kasir</span>
                </button>
            </form>

            <div style="margin-top: 16px; font-size: 11px; color: #64748b; text-align: center;">
                Sandi bawaan: <code>password123</code>
            </div>
        </div>
    </div>
</body>
</html>
