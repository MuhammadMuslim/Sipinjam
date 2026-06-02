<?php
require_once __DIR__ . '/config/functions.php';

if (isLoggedIn()) { header('Location: ' . BASE_URL . 'dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user     = dbFetchOne("SELECT * FROM users WHERE username = ? AND is_active = 1", [$username]);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user']    = $user;
        dbQuery("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']], 'i');
        header('Location: ' . BASE_URL . 'dashboard.php'); exit;
    }
    $error = 'Username atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login — SiPinjam</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    body{background:#001f5b;font-family:'Nunito',sans-serif}
    .login-box{width:380px}
    .login-logo a{color:#fff;font-weight:700;font-size:1.6rem}
    .card{border-radius:.75rem}
  </style>
</head>
<body class="hold-transition login-page">
<div class="login-box mx-auto mt-5">
  <div class="login-logo">
    <a href="#"><i class="fas fa-building mr-2"></i>SiPinjam</a>
  </div>
  <div class="card shadow">
    <div class="card-body p-4">
      <p class="login-box-msg text-muted mb-3">Masuk ke panel admin</p>
      <?php if ($error): ?>
        <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle mr-1"></i><?= e($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="input-group mb-3">
          <input type="text" name="username" class="form-control" placeholder="Username" value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
          <div class="input-group-append"><div class="input-group-text"><i class="fas fa-user"></i></div></div>
        </div>
        <div class="input-group mb-3">
          <input type="password" name="password" class="form-control" placeholder="Password" required>
          <div class="input-group-append"><div class="input-group-text"><i class="fas fa-lock"></i></div></div>
        </div>
        <button type="submit" class="btn btn-primary btn-block">
          <i class="fas fa-sign-in-alt mr-2"></i>Login
        </button>
      </form>
      <hr class="my-3">
      <p class="text-center text-muted mb-0" style="font-size:.8rem">
        Default: <strong>admin</strong> / <strong>Admin@123</strong>
      </p>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>