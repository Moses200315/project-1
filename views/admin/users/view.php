<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>

<div class="row g-4">
  <!-- Profile Card -->
  <div class="col-lg-4">
    <div class="card border-0 text-center" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-body p-4">
        <img src="<?= avatar_url($user['avatar']) ?>" class="rounded-circle mb-3" style="width:80px;height:80px;object-fit:cover;border:3px solid #2d6a4f;" alt="">
        <h5 class="fw-bold mb-0"><?= e($user['first_name'].' '.$user['last_name']) ?></h5>
        <p class="text-muted small"><?= e($user['email']) ?></p>
        <?= status_badge($user['status']) ?>

        <div class="mt-3 border-top pt-3 text-start">
          <div class="small text-muted mb-1"><i class="bi bi-phone me-2"></i><?= e($user['phone'] ?? 'N/A') ?></div>
          <div class="small text-muted mb-1"><i class="bi bi-calendar me-2"></i>Joined <?= format_date($user['created_at']) ?></div>
          <?php if($user['last_login']): ?><div class="small text-muted"><i class="bi bi-clock me-2"></i>Last login <?= time_ago($user['last_login']) ?></div><?php endif; ?>
          <?php if($user['bio']): ?><p class="small mt-2 text-muted"><?= e($user['bio']) ?></p><?php endif; ?>
        </div>

        <!-- Toggle Status -->
        <div class="mt-3 border-top pt-3">
          <form method="POST" action="<?= url('admin/toggleUserStatus') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
            <?php if($user['status']==='active'): ?>
              <select name="status" class="form-select form-select-sm mb-2">
                <option value="inactive">Set Inactive</option>
                <option value="banned">Ban Account</option>
              </select>
              <button class="btn btn-sm btn-warning w-100" onclick="return confirm('Change this user\'s status?')">Update Status</button>
            <?php else: ?>
              <input type="hidden" name="status" value="active">
              <button class="btn btn-sm btn-success w-100" onclick="return confirm('Activate this account?')">Activate Account</button>
            <?php endif; ?>
          </form>
        </div>
      </div>
    </div>

    <!-- Send Notification -->
    <div class="card border-0 mt-3" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-body">
        <h6 class="fw-bold mb-3">📬 Send Notification</h6>
        <form method="POST" action="<?= url('admin/sendNotification') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
          <input type="text" name="title" class="form-control form-control-sm mb-2" placeholder="Title" required>
          <textarea name="message" class="form-control form-control-sm mb-2" rows="2" placeholder="Message…" required></textarea>
          <select name="type" class="form-select form-select-sm mb-2">
            <option value="info">Info</option>
            <option value="success">Success</option>
            <option value="warning">Warning</option>
            <option value="error">Error</option>
          </select>
          <button class="btn btn-success btn-sm w-100">Send</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Details -->
  <div class="col-lg-8">
    <!-- Subscription -->
    <div class="card border-0 mb-3" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-header bg-white border-0 fw-bold p-3">💎 Subscription</div>
      <div class="card-body pt-0">
        <?php if($subscription): ?>
        <div class="row g-3">
          <div class="col-sm-4"><div class="text-muted small">Plan</div><div class="fw-semibold"><?= e($subscription['plan_name']) ?></div></div>
          <div class="col-sm-4"><div class="text-muted small">Status</div><?= status_badge($subscription['status']) ?></div>
          <div class="col-sm-4"><div class="text-muted small">Expires</div><div class="fw-semibold"><?= format_date($subscription['ends_at'],'d M Y') ?></div></div>
        </div>
        <?php else: ?><p class="text-muted small mb-0">No active subscription.</p><?php endif; ?>
      </div>
    </div>

    <!-- Recent Payments -->
    <div class="card border-0 mb-3" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-header bg-white border-0 fw-bold p-3">💳 Recent Payments</div>
      <div class="card-body pt-0">
        <?php if(!empty($payments)): ?>
        <div class="table-responsive"><table class="table table-sm mb-0">
          <thead><tr><th>Ref</th><th>Amount</th><th>Provider</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach($payments as $p): ?>
            <tr>
              <td class="small font-monospace"><?= e(substr($p['transaction_ref'],0,16)) ?>…</td>
              <td class="small fw-semibold"><?= format_currency((float)$p['amount']) ?></td>
              <td class="small"><?= e($p['provider']) ?></td>
              <td><?= status_badge($p['status']) ?></td>
              <td class="small text-muted"><?= format_date($p['created_at'],'d M Y') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php else: ?><p class="text-muted small mb-0">No payments yet.</p><?php endif; ?>
      </div>
    </div>

    <!-- Meal Plans -->
    <div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-header bg-white border-0 fw-bold p-3">📅 Meal Plans (<?= count($mealPlans) ?>)</div>
      <div class="card-body pt-0">
        <?php if(!empty($mealPlans)): ?>
          <?php foreach(array_slice($mealPlans,0,3) as $mp): ?>
          <div class="d-flex justify-content-between align-items-center border-bottom py-2">
            <div><div class="small fw-semibold"><?= e($mp['name']) ?></div>
              <div class="text-muted" style="font-size:.75rem;"><?= format_date($mp['week_start'],'d M') ?> – <?= format_date($mp['week_end'],'d M Y') ?></div></div>
            <?= status_badge($mp['status']) ?>
          </div>
          <?php endforeach; ?>
        <?php else: ?><p class="text-muted small mb-0">No meal plans.</p><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
