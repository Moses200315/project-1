<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">User & Subscription Report</h4>
    <p class="text-muted small mb-0">Customer growth and subscription analytics</p></div>
  <a href="<?= url('reports/exportCsv?type=users') ?>" class="btn btn-outline-success btn-sm">
    <i class="bi bi-download me-1"></i>Export CSV
  </a>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
  <?php foreach([
    ['Total Users',      $userStats['total'],           'primary', 'bi-people'],
    ['Active Users',     $userStats['active'],          'success', 'bi-person-check'],
    ['New This Month',   $userStats['new_this_month'],  'info',    'bi-person-plus'],
    ['Active Subs',      $subStats['active'],           'warning', 'bi-gem'],
  ] as [$l,$v,$c,$i]): ?>
  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 stat-card"><div class="card-body d-flex align-items-center gap-3">
      <div class="stat-icon bg-<?= $c ?> bg-opacity-10"><i class="bi <?= $i ?> text-<?= $c ?> fs-5"></i></div>
      <div><div class="text-muted small"><?= $l ?></div><div class="fw-bold fs-4"><?= number_format($v) ?></div></div>
    </div></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <!-- Subscription by Plan Donut -->
  <div class="col-md-4">
    <div class="card border-0 h-100" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-body">
        <h6 class="fw-bold mb-3">Subscribers by Plan</h6>
        <canvas id="planChart" height="180"></canvas>
        <ul class="list-unstyled mt-3 small">
          <?php foreach($byPlan as $p): ?>
          <li class="d-flex justify-content-between mb-1">
            <span><?= e($p['name']) ?></span>
            <strong><?= number_format($p['subscriber_count']) ?> active</strong>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- Recent Subscriptions -->
  <div class="col-md-8">
    <div class="card border-0 h-100" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-header bg-white border-0 fw-bold p-3">Recent Active Subscriptions</div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Customer</th><th>Plan</th><th>Status</th><th>Expires</th></tr></thead>
        <tbody>
          <?php foreach(array_slice($recentSubs,0,12) as $s): ?>
          <tr>
            <td><div class="small fw-semibold"><?= e($s['user_name']) ?></div>
              <div class="text-muted" style="font-size:.72rem;"><?= e($s['user_email']) ?></div></td>
            <td><span class="badge bg-success"><?= e($s['plan_name']) ?></span></td>
            <td><?= status_badge($s['status']) ?></td>
            <td class="small text-muted"><?= $s['ends_at'] ? format_date($s['ends_at'],'d M Y') : '—' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($recentSubs)): ?>
          <tr><td colspan="4" class="text-center text-muted small py-4">No subscriptions yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>

<?php $extraScripts = '<script>
new Chart(document.getElementById("planChart"),{type:"doughnut",data:{labels:'.json_encode(array_column($byPlan,"name")).',datasets:[{data:'.json_encode(array_column($byPlan,"subscriber_count")).',backgroundColor:["#2d6a4f","#52b788","#f4a261","#e76f51"],borderWidth:0}]},options:{cutout:"62%",plugins:{legend:{position:"bottom",labels:{boxWidth:12,font:{size:11}}}}}});
</script>';
?>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
