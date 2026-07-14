<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<style>
body{background:linear-gradient(135deg,#f0faf5,#e8f4ed);min-height:100vh;display:flex;align-items:center;font-family:'Segoe UI',system-ui,sans-serif;}
.auth-card{border:none;border-radius:20px;box-shadow:0 8px 40px rgba(45,106,79,.12);}
.form-control:focus{border-color:#2d6a4f;box-shadow:0 0 0 .2rem rgba(45,106,79,.2);}
</style>
</head>
<body>
<div class="container" style="max-width:420px;">
  <?= render_flash() ?>
  <?php $link = \Session::get('_debug_reset_link'); if($link): ?>
  <div class="alert alert-info mb-3">
    <strong>🔧 XAMPP Dev Mode:</strong> Reset link:<br>
    <a href="<?= e($link) ?>" class="small"><?= e($link) ?></a>
    <?php \Session::forget('_debug_reset_link'); ?>
  </div>
  <?php endif; ?>
  <div class="card auth-card">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-4">
        <div style="font-size:3rem">🔑</div>
        <h4 class="fw-bold mt-2">Forgot Password?</h4>
        <p class="text-muted small">Enter your email and we'll generate a reset link.</p>
      </div>
      <form method="POST" action="<?= url('auth/forgot') ?>">
        <?= csrf_field() ?>
        <div class="mb-4">
          <label class="form-label fw-semibold">Email Address</label>
          <input type="email" name="email" class="form-control form-control-lg"
                 placeholder="you@example.com" required autofocus>
        </div>
        <div class="d-grid">
          <button type="submit" class="btn btn-success btn-lg fw-semibold">
            <i class="bi bi-send me-2"></i>Send Reset Link
          </button>
        </div>
      </form>
      <p class="text-center text-muted small mt-3 mb-0">
        <a href="<?= url('auth/login') ?>" class="text-success text-decoration-none">← Back to Login</a>
      </p>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
