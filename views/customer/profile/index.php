<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_header.php'; ?>

<div class="row g-4">
  <!-- Avatar Card -->
  <div class="col-md-4">
    <div class="card border-0 text-center" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-body p-4">
        <img src="<?= avatar_url($profile['avatar']) ?>" class="rounded-circle mb-3"
             style="width:100px;height:100px;object-fit:cover;border:3px solid #2d6a4f;" alt="avatar" id="avatarPreview">
        <h5 class="fw-bold mb-0"><?= e($profile['first_name'].' '.$profile['last_name']) ?></h5>
        <p class="text-muted small"><?= e($profile['email']) ?></p>
        <?php if(!$isAdmin && $permissions): ?>
          <span class="badge bg-success"><?= e($permissions['plan_name']) ?> Plan</span>
        <?php endif; ?>

        <!-- Avatar Upload -->
        <form method="POST" action="<?= url('profile/uploadAvatar') ?>" enctype="multipart/form-data" class="mt-3">
          <?= csrf_field() ?>
          <label class="btn btn-outline-success btn-sm w-100 mb-2" for="avatarInput">
            <i class="bi bi-camera me-1"></i>Change Photo
          </label>
          <input type="file" id="avatarInput" name="avatar" accept="image/*" class="d-none"
                 onchange="previewAvatar(this);this.form.submit();">
        </form>

        <?php if(!$isAdmin && $permissions): ?>
        <div class="mt-3 border-top pt-3 text-start small">
          <div class="d-flex justify-content-between mb-1"><span class="text-muted">Plan</span><strong><?= e($permissions['plan_name']) ?></strong></div>
          <?php if($permissions['has_subscription']): ?>
          <div class="d-flex justify-content-between mb-1"><span class="text-muted">Expires</span><strong><?= format_date($permissions['ends_at'],'d M Y') ?></strong></div>
          <div class="d-flex justify-content-between"><span class="text-muted">Days Left</span><strong><?= $permissions['days_remaining'] ?></strong></div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <a href="<?= url('profile/downloads') ?>" class="btn btn-outline-info btn-sm w-100 mt-2">
      <i class="bi bi-download me-1"></i>Download History
    </a>
  </div>

  <!-- Profile + Password Forms -->
  <div class="col-md-8">
    <!-- Edit Profile -->
    <div class="card border-0 mb-4" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-header bg-white border-0 fw-bold p-4 pb-0">Edit Profile</div>
      <div class="card-body p-4">
        <form method="POST" action="<?= url('profile/update') ?>">
          <?= csrf_field() ?>
          <div class="row g-3 mb-3">
            <div class="col-md-6"><label class="form-label fw-semibold">First Name *</label>
              <input type="text" name="first_name" class="form-control" value="<?= e($profile['first_name']) ?>" required></div>
            <div class="col-md-6"><label class="form-label fw-semibold">Last Name *</label>
              <input type="text" name="last_name" class="form-control" value="<?= e($profile['last_name']) ?>" required></div>
          </div>
          <div class="mb-3"><label class="form-label fw-semibold">Email</label>
            <input type="email" class="form-control" value="<?= e($profile['email']) ?>" disabled>
            <div class="text-muted small">Email cannot be changed.</div></div>
          <?php if(!$isAdmin): ?>
          <div class="mb-3"><label class="form-label fw-semibold">Phone</label>
            <input type="tel" name="phone" class="form-control" value="<?= e($profile['phone'] ?? '') ?>" placeholder="+233 244 000 000"></div>
          <div class="mb-3"><label class="form-label fw-semibold">Bio</label>
            <textarea name="bio" class="form-control" rows="3" placeholder="Tell us a bit about yourself…"><?= e($profile['bio'] ?? '') ?></textarea></div>
          <?php endif; ?>
          <button type="submit" class="btn btn-success fw-semibold px-4"><i class="bi bi-check-lg me-2"></i>Save Changes</button>
        </form>
      </div>
    </div>

    <!-- Change Password -->
    <div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-header bg-white border-0 fw-bold p-4 pb-0">Change Password</div>
      <div class="card-body p-4">
        <form method="POST" action="<?= url('profile/changePassword') ?>">
          <?= csrf_field() ?>
          <div class="mb-3"><label class="form-label fw-semibold">Current Password *</label>
            <input type="password" name="current_password" class="form-control" required placeholder="Your current password"></div>
          <div class="mb-3"><label class="form-label fw-semibold">New Password *</label>
            <input type="password" name="new_password" class="form-control" required placeholder="Min 8 chars, upper, lower, number, symbol"></div>
          <div class="mb-4"><label class="form-label fw-semibold">Confirm New Password *</label>
            <input type="password" name="confirm_password" class="form-control" required placeholder="Repeat new password"></div>
          <button type="submit" class="btn btn-warning fw-semibold px-4"><i class="bi bi-shield-lock me-2"></i>Update Password</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function previewAvatar(input){
  if(input.files&&input.files[0]){
    const reader=new FileReader();
    reader.onload=e=>document.getElementById('avatarPreview').src=e.target.result;
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_footer.php'; ?>
