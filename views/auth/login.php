<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<style>
body{background:linear-gradient(135deg,#f0faf5 0%,#e8f4ed 100%);min-height:100vh;display:flex;align-items:center;font-family:'Segoe UI',system-ui,sans-serif;}
.auth-card{border:none;border-radius:20px;box-shadow:0 8px 40px rgba(45,106,79,.12);}
.auth-brand{font-weight:800;color:#2d6a4f;font-size:1.5rem;}
.form-control:focus{border-color:#2d6a4f;box-shadow:0 0 0 .2rem rgba(45,106,79,.2);}
.btn-auth{background:#2d6a4f;border:none;padding:.75rem;font-weight:600;border-radius:10px;}
.btn-auth:hover{background:#245a40;}
.divider{display:flex;align-items:center;gap:.75rem;color:#adb5bd;font-size:.82rem;}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:#dee2e6;}
.role-tab .nav-link{color:#6c757d;border-radius:10px;font-size:.88rem;}
.role-tab .nav-link.active{background:#2d6a4f;color:#fff;border-color:#2d6a4f;}
</style>
</head>
<body>
<div class="container" style="max-width:440px;">
  <?= render_flash() ?>
  <div class="card auth-card">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-4">
        <div class="auth-brand">🍽️ <?= APP_NAME ?></div>
        <p class="text-muted small mt-1">Sign in to your account</p>
      </div>

      <!-- Role Tabs -->
      <ul class="nav nav-pills role-tab mb-4 justify-content-center gap-2">
        <li class="nav-item">
          <a class="nav-link <?= old('role','customer')==='customer'?'active':'' ?>"
             href="#" onclick="setRole('customer');return false;">
            <i class="bi bi-person me-1"></i>Customer
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= old('role')==='admin'?'active':'' ?>"
             href="#" onclick="setRole('admin');return false;">
            <i class="bi bi-shield-lock me-1"></i>Admin
          </a>
        </li>
      </ul>

      <form method="POST" action="<?= url('auth/login') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="role" id="roleInput" value="<?= old('role','customer') ?>">

        <div class="mb-3">
          <label class="form-label fw-semibold">Email Address</label>
          <input type="email" name="email" class="form-control form-control-lg"
                 value="<?= old('email') ?>" placeholder="you@example.com" required autofocus>
        </div>
        <div class="mb-3">
          <div class="d-flex justify-content-between">
            <label class="form-label fw-semibold">Password</label>
            <a href="<?= url('auth/forgot') ?>" class="small text-success text-decoration-none">Forgot?</a>
          </div>
          <div class="input-group">
            <input type="password" name="password" id="pwField" class="form-control form-control-lg"
                   placeholder="Your password" required>
            <button type="button" class="btn btn-outline-secondary" onclick="togglePw()">
              <i class="bi bi-eye" id="pwEyeIcon"></i>
            </button>
          </div>
        </div>

        <div class="d-grid mt-4">
          <button type="submit" class="btn btn-auth btn-primary btn-lg text-white">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
          </button>
        </div>
      </form>

      <div class="divider my-4">or</div>
      <p class="text-center text-muted small">
        Don't have an account?
        <a href="<?= url('auth/register') ?>" class="text-success fw-semibold text-decoration-none">Create one free</a>
      </p>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function setRole(r){document.getElementById('roleInput').value=r;document.querySelectorAll('.role-tab .nav-link').forEach(l=>l.classList.remove('active'));event.target.closest('.nav-link').classList.add('active');}
function togglePw(){const f=document.getElementById('pwField'),i=document.getElementById('pwEyeIcon');f.type=f.type==='password'?'text':'password';i.className=f.type==='password'?'bi bi-eye':'bi bi-eye-slash';}
</script>
</body>
</html>