<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= asset('css/style.css') ?>">
<style>
body{background:linear-gradient(135deg,#f0faf5,#e8f4ed);min-height:100vh;display:flex;align-items:center;font-family:'Segoe UI',system-ui,sans-serif;}
.auth-card{border:none;border-radius:20px;box-shadow:0 8px 40px rgba(45,106,79,.12);}
.auth-brand{font-weight:800;color:#2d6a4f;font-size:1.5rem;}
.form-control:focus{border-color:#2d6a4f;box-shadow:0 0 0 .2rem rgba(45,106,79,.2);}
.btn-auth{background:#2d6a4f;border:none;padding:.75rem;font-weight:600;border-radius:10px;}
.strength-bar{height:4px;border-radius:2px;transition:all .3s;}
</style>
</head>
<body>
<div class="container py-4" style="max-width:500px;">
  <?= render_flash() ?>
  <div class="card auth-card">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-4">
        <div class="auth-brand">🍽️ <?= APP_NAME ?></div>
        <p class="text-muted small mt-1">Create your free account</p>
      </div>

      <form method="POST" action="<?= url('auth/register') ?>" novalidate>
        <?= csrf_field() ?>

        <div class="row g-3 mb-3">
          <div class="col-6">
            <label class="form-label fw-semibold">First Name</label>
            <input type="text" name="first_name" class="form-control"
                   value="<?= old('first_name') ?>" placeholder="Samson" required>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Last Name</label>
            <input type="text" name="last_name" class="form-control"
                   value="<?= old('last_name') ?>" placeholder="Ligima" required>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Email Address</label>
          <input type="email" name="email" class="form-control"
                 value="<?= old('email') ?>" placeholder="you@example.com" required>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Phone <span class="text-muted fw-normal">(optional)</span></label>
          <input type="tel" name="phone" class="form-control"
                 value="<?= old('phone') ?>" placeholder="+233 244 000 000">
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Password</label>
          <div class="input-group">
            <input type="password" name="password" id="pwField" class="form-control"
                   placeholder="Min 8 chars, upper, lower, number, symbol"
                   oninput="checkStrength(this.value)" required>
            <button type="button" class="btn btn-outline-secondary" onclick="togglePw('pwField','eye1')">
              <i class="bi bi-eye" id="eye1"></i>
            </button>
          </div>
          <div class="mt-1">
            <div class="d-flex gap-1">
              <div class="strength-bar bg-secondary flex-fill" id="sb1"></div>
              <div class="strength-bar bg-secondary flex-fill" id="sb2"></div>
              <div class="strength-bar bg-secondary flex-fill" id="sb3"></div>
              <div class="strength-bar bg-secondary flex-fill" id="sb4"></div>
            </div>
            <small class="text-muted" id="strengthLabel">Enter a password</small>
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold">Confirm Password</label>
          <div class="input-group">
            <input type="password" name="password_confirm" id="pwConf" class="form-control"
                   placeholder="Repeat password" required>
            <button type="button" class="btn btn-outline-secondary" onclick="togglePw('pwConf','eye2')">
              <i class="bi bi-eye" id="eye2"></i>
            </button>
          </div>
        </div>

        <div class="d-grid">
          <button type="submit" class="btn btn-auth btn-primary btn-lg text-white">
            <i class="bi bi-person-plus me-2"></i>Create Account
          </button>
        </div>

        <p class="text-center text-muted small mt-3 mb-0">
          Already have an account?
          <a href="<?= url('auth/login') ?>" class="text-success fw-semibold text-decoration-none">Sign in</a>
        </p>
      </form>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePw(id,iconId){const f=document.getElementById(id),i=document.getElementById(iconId);f.type=f.type==='password'?'text':'password';i.className=f.type==='password'?'bi bi-eye':'bi bi-eye-slash';}
function checkStrength(v){
  const bars=[1,2,3,4].map(i=>document.getElementById('sb'+i));
  const lbl=document.getElementById('strengthLabel');
  let score=0;
  if(v.length>=8)score++;if(/[A-Z]/.test(v))score++;if(/[0-9]/.test(v))score++;if(/[\W_]/.test(v))score++;
  const colors=['#dc3545','#fd7e14','#ffc107','#28a745'];
  const labels=['Weak','Fair','Good','Strong'];
  bars.forEach((b,i)=>{b.style.background=i<score?colors[score-1]:'#dee2e6';});
  lbl.textContent=score>0?labels[score-1]:'Enter a password';
  lbl.style.color=score>0?colors[score-1]:'#6c757d';
}
</script>
</body></html>
