<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Recipe Analytics</h4>
    <p class="text-muted small mb-0">Popularity, views & download statistics</p></div>
  <a href="<?= url('reports/exportCsv?type=recipes') ?>" class="btn btn-outline-success btn-sm">
    <i class="bi bi-download me-1"></i>Export CSV
  </a>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
  <?php foreach([
    ['Total Recipes', $stats['total'], 'primary', 'bi-journal-richtext'],
    ['Published',     $stats['published'], 'success', 'bi-check-circle'],
    ['Premium',       $stats['premium'],   'warning', 'bi-star'],
    ['Total Downloads',$downloadStats['total'], 'info', 'bi-download'],
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
  <!-- Top Viewed -->
  <div class="col-lg-6">
    <div class="card border-0 h-100" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-header bg-white border-0 fw-bold p-3">👁️ Most Viewed Recipes</div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>#</th><th>Recipe</th><th>Category</th><th>Views</th></tr></thead>
        <tbody>
          <?php foreach(array_slice($topViewed,0,12) as $i=>$r): ?>
          <tr>
            <td class="text-muted small"><?= $i+1 ?></td>
            <td class="small fw-semibold"><?= e(truncate($r['title'],32)) ?></td>
            <td class="small text-muted"><?= e($r['category_name']) ?></td>
            <td><span class="badge bg-info text-dark"><?= number_format($r['views']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <!-- Top Downloaded -->
  <div class="col-lg-6">
    <div class="card border-0 h-100" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-header bg-white border-0 fw-bold p-3">📥 Most Downloaded Recipes</div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>#</th><th>Recipe</th><th>Downloads</th></tr></thead>
        <tbody>
          <?php if(empty($topDownloads)): ?>
          <tr><td colspan="3" class="text-center text-muted small py-4">No downloads yet.</td></tr>
          <?php else: ?>
          <?php foreach(array_slice($topDownloads,0,12) as $i=>$d): ?>
          <tr>
            <td class="text-muted small"><?= $i+1 ?></td>
            <td class="small fw-semibold"><?= e(truncate($d['title'],35)) ?></td>
            <td><span class="badge bg-success"><?= number_format($d['download_count']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>

  <!-- Category Chart -->
  <div class="col-12">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-body"><h6 class="fw-bold mb-3">Recipes by Category</h6>
        <canvas id="catChart" height="60"></canvas>
      </div>
    </div>
  </div>
</div>

<?php $extraScripts = '<script>
new Chart(document.getElementById("catChart"),{type:"bar",data:{labels:'.json_encode(array_column($byCategory,"name")).',datasets:[{label:"Published Recipes",data:'.json_encode(array_column($byCategory,"recipe_count")).',backgroundColor:"rgba(82,183,136,.75)",borderRadius:6}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:"#f0f4f8"}}},responsive:true}});
</script>';
?>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
