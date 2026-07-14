<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Reports & Analytics</h4><p class="text-muted small mb-0">Year: <?= $year ?></p></div>
  <div class="d-flex gap-2">
    <a href="<?= url('reports/exportCsv?type=revenue&year='.$year) ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-download me-1"></i>Revenue CSV</a>
    <a href="<?= url('reports/exportCsv?type=users') ?>" class="btn btn-outline-info btn-sm"><i class="bi bi-download me-1"></i>Users CSV</a>
  </div>
</div>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
  <?php foreach([
    ['Total Revenue',format_currency($payStats['total_revenue']),'bi-graph-up','success'],
    ['Monthly Revenue',format_currency($payStats['monthly_revenue']),'bi-calendar-month','primary'],
    ['Active Subscribers',$subStats['active'],'bi-people','info'],
    ['Total Downloads',$downloadStats['total'],'bi-download','warning'],
  ] as [$l,$v,$i,$c]): ?>
  <div class="col-sm-6 col-xl-3"><div class="card border-0 stat-card"><div class="card-body d-flex align-items-center gap-3">
    <div class="stat-icon bg-<?= $c ?> bg-opacity-10"><i class="bi <?= $i ?> text-<?= $c ?> fs-5"></i></div>
    <div><div class="text-muted small"><?= $l ?></div><div class="fw-bold fs-5"><?= $v ?></div></div>
  </div></div></div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-xl-8">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-body">
        <h6 class="fw-bold mb-3">Monthly Revenue – <?= $year ?></h6>
        <canvas id="revenueChart" height="80"></canvas>
      </div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-body">
        <h6 class="fw-bold mb-3">Revenue by Provider</h6>
        <canvas id="providerChart" height="160"></canvas>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-header bg-white border-0 fw-bold p-3">Top Downloaded Recipes</div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Recipe</th><th>Downloads</th></tr></thead>
        <tbody>
          <?php foreach(array_slice($topDownloads,0,8) as $d): ?>
          <tr><td class="small"><?= e(truncate($d['title'],40)) ?></td><td><span class="badge bg-info text-dark"><?= $d['download_count'] ?></span></td></tr>
          <?php endforeach; ?>
          <?php if(empty($topDownloads)): ?><tr><td colspan="2" class="text-center text-muted small py-3">No downloads yet.</td></tr><?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-header bg-white border-0 fw-bold p-3">Recipes by Category</div>
      <div class="card-body"><canvas id="categoryChart" height="180"></canvas></div>
    </div>
  </div>
</div>

<?php $extraScripts = '<script>
new Chart(document.getElementById("revenueChart"),{type:"bar",data:{labels:["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],datasets:[{label:"Revenue (GHS)",data:'.json_encode(array_values($monthlyRevenue)).',backgroundColor:"rgba(45,106,79,.7)",borderRadius:6}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}},responsive:true}});
new Chart(document.getElementById("providerChart"),{type:"doughnut",data:{labels:'.json_encode(array_column($byProvider,"provider")).',datasets:[{data:'.json_encode(array_column($byProvider,"total")).',backgroundColor:["#2d6a4f","#52b788","#f4a261","#e76f51"],borderWidth:0}]},options:{cutout:"60%",plugins:{legend:{position:"bottom",labels:{boxWidth:12}}}}});
new Chart(document.getElementById("categoryChart"),{type:"bar",data:{labels:'.json_encode(array_column($byCategory,"name")).',datasets:[{label:"Recipes",data:'.json_encode(array_column($byCategory,"recipe_count")).',backgroundColor:"rgba(82,183,136,.7)",borderRadius:4}]},options:{indexAxis:"y",plugins:{legend:{display:false}},scales:{x:{beginAtZero:true}}}});
</script>';
?>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
