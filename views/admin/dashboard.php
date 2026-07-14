<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <?php
  // Safely extract scalar values from stats arrays with defensive checks
  $userStats = is_array($userStats) ? $userStats : [];
  $payStats = is_array($payStats) ? $payStats : [];
  $subStats = is_array($subStats) ? $subStats : [];
  $recipeStats = is_array($recipeStats) ? $recipeStats : [];

  $totalCustomers = is_scalar($userStats['total'] ?? 0) ? (int)$userStats['total'] : 0;
  $newCustomers = is_scalar($userStats['new_this_month'] ?? 0) ? (int)$userStats['new_this_month'] : 0;
  $totalRevenue = is_scalar($payStats['total_revenue'] ?? 0) ? (float)$payStats['total_revenue'] : 0;
  $monthlyRevenueStat = is_scalar($payStats['monthly_revenue'] ?? 0) ? (float)$payStats['monthly_revenue'] : 0;
  $activeSubs = is_scalar($subStats['active'] ?? 0) ? (int)$subStats['active'] : 0;
  $totalSubs = is_scalar($subStats['total'] ?? 0) ? (int)$subStats['total'] : 0;
  $publishedRecipes = is_scalar($recipeStats['published'] ?? 0) ? (int)$recipeStats['published'] : 0;
  $totalViews = is_scalar($recipeStats['total_views'] ?? 0) ? (int)$recipeStats['total_views'] : 0;

  $stats = [
    ['label'=>'Total Customers','value'=>$totalCustomers,'icon'=>'bi-people-fill','bg'=>'bg-primary','sub'=>$newCustomers.' new this month'],
    ['label'=>'Total Revenue','value'=>format_currency($totalRevenue),'icon'=>'bi-currency-dollar','bg'=>'bg-success','sub'=>format_currency($monthlyRevenueStat).' this month'],
    ['label'=>'Active Subscriptions','value'=>$activeSubs,'icon'=>'bi-calendar-check-fill','bg'=>'bg-warning','sub'=>$totalSubs.' total ever'],
    ['label'=>'Published Recipes','value'=>$publishedRecipes,'icon'=>'bi-journal-richtext','bg'=>'bg-info','sub'=>$totalViews.' total views'],
  ];
  foreach($stats as $s): ?>
  <div class="col-sm-6 col-xl-3">
    <div class="card stat-card border-0 h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="text-muted small fw-semibold text-uppercase"><?= e($s['label']) ?></div>
            <div class="fs-3 fw-bold mt-1"><?= e($s['value']) ?></div>
            <div class="text-muted small mt-1"><?= e($s['sub']) ?></div>
          </div>
          <div class="stat-icon <?= $s['bg'] ?> bg-opacity-10">
            <i class="bi <?= $s['icon'] ?> <?= str_replace('bg-','text-',$s['bg']) ?>"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
  <div class="col-xl-8">
    <div class="card border-0 h-100" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-body">
        <h6 class="fw-bold mb-3">Monthly Revenue (<?= date('Y') ?>)</h6>
        <canvas id="revenueChart" height="90"></canvas>
      </div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card border-0 h-100" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-body">
        <h6 class="fw-bold mb-3">Subscribers by Plan</h6>
        <canvas id="planChart" height="160"></canvas>
      </div>
    </div>
  </div>
</div>

<!-- Tables Row -->
<div class="row g-3">
  <!-- Recent Payments -->
  <div class="col-xl-6">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3 px-4">
        <h6 class="mb-0 fw-bold">Recent Payments</h6>
        <a href="<?= url('payments/adminIndex') ?>" class="btn btn-sm btn-outline-success">View All</a>
      </div>
      <div class="card-body px-4 pb-3 pt-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead><tr><th>User</th><th>Amount</th><th>Provider</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach($recentPayments as $p): ?>
              <tr>
                <td class="small"><?= e($p['user_name']) ?></td>
                <td class="small fw-semibold"><?= format_currency((float)$p['amount']) ?></td>
                <td class="small"><?= e($p['provider']) ?></td>
                <td><?= status_badge($p['status']) ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($recentPayments)): ?>
              <tr><td colspan="4" class="text-center text-muted small py-3">No payments yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Top Recipes -->
  <div class="col-xl-6">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center py-3 px-4">
        <h6 class="mb-0 fw-bold">Top Viewed Recipes</h6>
        <a href="<?= url('recipes/adminIndex') ?>" class="btn btn-sm btn-outline-success">View All</a>
      </div>
      <div class="card-body px-4 pb-3 pt-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover">
            <thead><tr><th>Recipe</th><th>Category</th><th>Views</th></tr></thead>
            <tbody>
              <?php foreach($topRecipes as $r): ?>
              <tr>
                <td class="small fw-semibold"><?= e(truncate($r['title'],35)) ?></td>
                <td class="small text-muted"><?= e($r['category_name']) ?></td>
                <td class="small"><span class="badge bg-info text-dark"><?= number_format($r['views']) ?></span></td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($topRecipes)): ?>
              <tr><td colspan="3" class="text-center text-muted small py-3">No recipes yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
// Absolute final defense - ensure all chart data are scalar-only arrays
$chartRevenue = [];
if (isset($monthlyRevenue) && is_array($monthlyRevenue)) {
    foreach ($monthlyRevenue as $v) {
        if (is_scalar($v)) {
            $chartRevenue[] = $v;
        } else {
            $chartRevenue[] = 0;
        }
    }
} else {
    $chartRevenue = [0,0,0,0,0,0,0,0,0,0,0,0];
}

$chartLabels = [];
if (isset($planNames) && is_array($planNames)) {
    foreach ($planNames as $v) {
        if (is_scalar($v)) {
            $chartLabels[] = $v;
        } else {
            $chartLabels[] = '';
        }
    }
} else {
    $chartLabels = [];
}

$chartData = [];
if (isset($planCounts) && is_array($planCounts)) {
    foreach ($planCounts as $v) {
        if (is_scalar($v)) {
            $chartData[] = $v;
        } else {
            $chartData[] = 0;
        }
    }
} else {
    $chartData = [];
}
?>
<script>
// Revenue Chart
const rCtx = document.getElementById('revenueChart');
new Chart(rCtx, {
  type: 'line',
  data: {
    labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
    datasets: [{
      label: 'Revenue (GHS)',
      data: <?= json_encode(array_values($chartRevenue)) ?>,
      borderColor: '#2d6a4f', backgroundColor: 'rgba(45,106,79,.08)',
      tension: 0.4, fill: true, pointRadius: 4, pointBackgroundColor:'#2d6a4f'
    }]
  },
  options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,grid:{color:'#f0f4f8'}}}, responsive:true }
});

// Plan Pie Chart
const pCtx = document.getElementById('planChart');
new Chart(pCtx, {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_values($chartLabels)) ?>,
    datasets: [{
      data: <?= json_encode(array_values($chartData)) ?>,
      backgroundColor: ['#2d6a4f','#52b788','#f4a261','#e76f51'],
      borderWidth: 0
    }]
  },
  options: { cutout:'65%', plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:11}}}} }
});
</script>

<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
