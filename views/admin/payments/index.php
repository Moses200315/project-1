<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>
<div class="row g-3 mb-4">
  <?php foreach([
    ['Total Revenue',format_currency($stats['total_revenue']),'success','bi-graph-up'],
    ['This Month',format_currency($stats['monthly_revenue']),'info','bi-calendar-month'],
    ['Successful',$stats['success'],'primary','bi-check-circle'],
    ['Failed',$stats['failed'],'danger','bi-x-circle'],
  ] as [$l,$v,$c,$i]): ?>
  <div class="col-sm-6 col-xl-3"><div class="card border-0 stat-card"><div class="card-body d-flex align-items-center gap-3">
    <div class="stat-icon bg-<?= $c ?> bg-opacity-10"><i class="bi <?= $i ?> text-<?= $c ?> fs-5"></i></div>
    <div><div class="text-muted small"><?= $l ?></div><div class="fw-bold fs-5"><?= $v ?></div></div>
  </div></div></div>
  <?php endforeach; ?>
</div>

<div class="card border-0 mb-3" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
  <div class="card-body">
    <form method="GET" action="<?= url('payments/adminIndex') ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <select name="provider" class="form-select">
          <option value="">All Providers</option>
          <?php foreach($providers as $p): ?><option value="<?= $p ?>" <?= $provider===$p?'selected':'' ?>><?= $p ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <select name="status" class="form-select">
          <option value="">All Statuses</option>
          <?php foreach(['pending','success','failed','refunded'] as $s): ?>
            <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filter</button></div>
      <div class="col-md-2"><a href="<?= url('reports/exportCsv?type=revenue') ?>" class="btn btn-outline-success w-100"><i class="bi bi-download me-1"></i>Export CSV</a></div>
    </form>
  </div>
</div>

<div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
  <div class="card-body p-0">
    <div class="table-responsive"><table class="table table-hover mb-0">
      <thead><tr><th>Reference</th><th>Customer</th><th>Amount</th><th>Provider</th><th>Plan</th><th>Status</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <?php foreach($rows as $p): ?>
        <tr>
          <td><code class="small"><?= e(substr($p['transaction_ref'],0,18)) ?>…</code></td>
          <td><div class="small fw-semibold"><?= e($p['user_name']) ?></div><div class="text-muted" style="font-size:.72rem;"><?= e($p['user_email']) ?></div></td>
          <td class="fw-semibold small"><?= format_currency((float)$p['amount']) ?></td>
          <td class="small"><?= e($p['provider']) ?></td>
          <td class="small"><?= e($p['plan_name'] ?? '—') ?></td>
          <td><?= status_badge($p['status']) ?></td>
          <td class="small text-muted"><?= format_date($p['created_at'],'d M Y') ?></td>
          <td><a href="<?= url('payments/adminView/'.$p['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($rows)): ?><tr><td colspan="8" class="text-center py-4 text-muted">No payments found.</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
  <?php if($pager['total_pages']>1): ?><div class="card-footer bg-white"><?= render_pagination($pager) ?></div><?php endif; ?>
</div>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
