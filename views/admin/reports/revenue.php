<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h4 class="fw-bold mb-0">Revenue Report</h4>
    <p class="text-muted small mb-0">Financial summary for <?= $year ?></p>
  </div>
  <div class="d-flex gap-2">
    <form method="GET" action="<?= url('reports/revenue') ?>" class="d-flex gap-2">
      <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
        <?php for($y=date('Y');$y>=date('Y')-3;$y--): ?>
          <option value="<?= $y ?>" <?= $year==$y?'selected':'' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </form>
    <a href="<?= url('reports/exportCsv?type=revenue&year='.$year) ?>" class="btn btn-outline-success btn-sm">
      <i class="bi bi-download me-1"></i>Export CSV
    </a>
  </div>
</div>

<!-- KPI Summary -->
<div class="row g-3 mb-4">
  <?php foreach([
    ['Total Revenue', format_currency($stats['total_revenue']), 'success', 'bi-graph-up'],
    ['This Month',   format_currency($stats['monthly_revenue']), 'info',    'bi-calendar-month'],
    ['Successful Txn', number_format($stats['success']),          'primary', 'bi-check-circle'],
    ['Failed Txn',     number_format($stats['failed']),           'danger',  'bi-x-circle'],
  ] as [$l,$v,$c,$i]): ?>
  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 stat-card">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon bg-<?= $c ?> bg-opacity-10"><i class="bi <?= $i ?> text-<?= $c ?> fs-5"></i></div>
        <div><div class="text-muted small"><?= $l ?></div><div class="fw-bold fs-5"><?= $v ?></div></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Revenue Chart -->
<div class="card border-0 mb-4" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
  <div class="card-body">
    <h6 class="fw-bold mb-3">Monthly Revenue – <?= $year ?></h6>
    <canvas id="revenueChart" height="70"></canvas>
  </div>
</div>

<!-- Provider breakdown -->
<div class="row g-3 mb-4">
  <div class="col-md-5">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-body"><h6 class="fw-bold mb-3">By Provider</h6>
        <canvas id="providerChart" height="180"></canvas>
      </div>
    </div>
  </div>
  <div class="col-md-7">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-body p-0">
        <div class="p-3 fw-bold border-bottom">Recent Transactions</div>
        <div class="table-responsive"><table class="table table-sm mb-0">
          <thead><tr><th>Reference</th><th>User</th><th>Amount</th><th>Provider</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach(array_slice($payments,0,15) as $p): ?>
            <tr>
              <td><code class="small"><?= e(substr($p['transaction_ref'],0,16)) ?>…</code></td>
              <td class="small"><?= e($p['user_name']) ?></td>
              <td class="small fw-semibold"><?= format_currency((float)$p['amount']) ?></td>
              <td class="small"><?= e($p['provider']) ?></td>
              <td><?= status_badge($p['status']) ?></td>
              <td class="small text-muted"><?= format_date($p['created_at'],'d M Y') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      </div>
    </div>
  </div>
</div>

<?php $extraScripts = '<script>
new Chart(document.getElementById("revenueChart"),{type:"line",data:{labels:["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],datasets:[{label:"Revenue (GHS)",data:'.json_encode(array_values($monthlyRevenue)).',borderColor:"#2d6a4f",backgroundColor:"rgba(45,106,79,.08)",tension:.4,fill:true,pointRadius:4,pointBackgroundColor:"#2d6a4f"}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}},responsive:true}});
new Chart(document.getElementById("providerChart"),{type:"doughnut",data:{labels:'.json_encode(array_column($byProvider,"provider")).',datasets:[{data:'.json_encode(array_column($byProvider,"total")).',backgroundColor:["#2d6a4f","#52b788","#f4a261","#e76f51"],borderWidth:0}]},options:{cutout:"60%",plugins:{legend:{position:"bottom"}}}});
</script>';
?>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
