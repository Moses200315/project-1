<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>
<div class="row g-3 mb-4">
  <?php foreach([['Active',$stats['active'],'success'],['Expired',$stats['expired'],'secondary'],['Cancelled',$stats['cancelled'],'warning'],['This Month',$stats['new_this_month'],'info']] as [$l,$v,$c]): ?>
  <div class="col-sm-6 col-xl-3"><div class="card border-0 stat-card"><div class="card-body d-flex align-items-center gap-3">
    <div class="stat-icon bg-<?= $c ?> bg-opacity-10"><i class="bi bi-calendar-check text-<?= $c ?> fs-5"></i></div>
    <div><div class="text-muted small"><?= $l ?></div><div class="fw-bold fs-4"><?= number_format($v) ?></div></div>
  </div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-body"><h6 class="fw-bold mb-3">By Plan</h6>
        <?php foreach($byPlan as $p): ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="small"><?= e($p['name']) ?></span>
          <span class="badge bg-success"><?= $p['subscriber_count'] ?> active</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card border-0 mb-3" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-header bg-white border-0 py-3 px-4 d-flex justify-content-between">
        <h6 class="fw-bold mb-0">Subscriptions</h6>
        <form method="GET" action="<?= url('subscriptions/adminIndex') ?>" class="d-flex gap-2">
          <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All</option>
            <?php foreach(['active','expired','cancelled','pending'] as $s): ?>
              <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <div class="table-responsive"><table class="table table-sm table-hover mb-0">
        <thead><tr><th>Customer</th><th>Plan</th><th>Status</th><th>Start</th><th>Expires</th></tr></thead>
        <tbody>
          <?php foreach($rows as $s): ?>
          <tr>
            <td><div class="fw-semibold small"><?= e($s['user_name']) ?></div><div class="text-muted" style="font-size:.72rem;"><?= e($s['user_email']) ?></div></td>
            <td><span class="badge bg-success"><?= e($s['plan_name']) ?></span></td>
            <td><?= status_badge($s['status']) ?></td>
            <td class="small text-muted"><?= $s['starts_at'] ? format_date($s['starts_at'],'d M Y') : '—' ?></td>
            <td class="small text-muted"><?= $s['ends_at'] ? format_date($s['ends_at'],'d M Y') : '—' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($rows)): ?><tr><td colspan="5" class="text-center py-3 text-muted small">No subscriptions found.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
      <?php if($pager['total_pages']>1): ?><div class="card-footer bg-white"><?= render_pagination($pager) ?></div><?php endif; ?>
    </div>
  </div>
</div>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
