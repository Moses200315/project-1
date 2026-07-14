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
  <div class="card auth-card">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-4">
        <div style="font-size:3rem">🔒</div>
        <h4 class="fw-bold mt-2">Set New Password</h4>
        <p class="text-muted small">Choose a strong new password for your account.</p>
      </div>
      <form method="POST" action="<?= url('auth/reset/' . e($token)) ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label fw-semibold">New Password</label>
          <input type="password" name="password" class="form-control form-control-lg"
                 placeholder="Min 8 chars" required autofocus>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold">Confirm New Password</label>
          <input type="password" name="password_confirm" class="form-control form-control-lg"
                 placeholder="Repeat new password" required>
        </div>
        <div class="d-grid">
          <button type="submit" class="btn btn-success btn-lg fw-semibold">
            <i class="bi bi-shield-check me-2"></i>Reset Password
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
