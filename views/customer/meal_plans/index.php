<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">📅 My Meal Plans</h4>
  <?php if($permissions['meal_plan_limit']===0 || count($plans??[]) < $permissions['meal_plan_limit']): ?>
  <a href="<?= url('mealplans/create') ?>" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>New Plan</a>
  <?php endif; ?>
</div>

<?php if(!$permissions['has_subscription']): ?>
<div class="alert alert-info d-flex justify-content-between align-items-center mb-4">
  <span><i class="bi bi-info-circle me-2"></i>Free plan allows 1 meal plan. <strong>Upgrade</strong> for unlimited plans.</span>
  <a href="<?= url('subscriptions/index') ?>" class="btn btn-sm btn-success">Upgrade</a>
</div>
<?php endif; ?>

<?php if(empty($plans)): ?>
<div class="text-center py-5">
  <div style="font-size:3rem;">📅</div>
  <h5 class="text-muted mt-3">No meal plans yet</h5>
  <p class="text-muted small">Create your first weekly meal plan to get started.</p>
  <a href="<?= url('mealplans/create') ?>" class="btn btn-success px-4"><i class="bi bi-plus-lg me-2"></i>Create Plan</a>
</div>
<?php else: ?>
<div class="row g-3">
  <?php foreach($plans as $mp): ?>
  <div class="col-md-6 col-xl-4">
    <div class="card border-0 h-100" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);<?= $mp['status']==='active'?'border-left:4px solid #2d6a4f!important;':'' ?>">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <h6 class="fw-bold mb-0"><?= e($mp['name']) ?></h6>
          <?= status_badge($mp['status']) ?>
        </div>
        <div class="text-muted small mb-2">
          <i class="bi bi-calendar3 me-1"></i><?= format_date($mp['week_start'],'d M') ?> – <?= format_date($mp['week_end'],'d M Y') ?>
        </div>
        <div class="text-muted small mb-3">
          <i class="bi bi-journal-richtext me-1"></i><?= (int)$mp['recipe_count'] ?> recipe slots assigned
        </div>
        <div class="d-flex gap-2">
          <a href="<?= url('mealplans/view/'.$mp['id']) ?>" class="btn btn-sm btn-success flex-fill">Open Plan</a>
          <a href="<?= url('mealplans/edit/'.$mp['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
          <form method="POST" action="<?= url('mealplans/delete/'.$mp['id']) ?>" class="d-inline"
                onsubmit="return confirm('Delete this meal plan?')">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_footer.php'; ?>
