<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_header.php'; ?>
<div class="card border-0 mx-auto" style="max-width:600px;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
  <div class="card-header bg-white border-0 fw-bold p-4 pb-0"><h5 class="mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Meal Plan</h5></div>
  <div class="card-body p-4">
    <form method="POST" action="<?= url('mealplans/update/'.$plan['id']) ?>">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label fw-semibold">Plan Name *</label>
        <input type="text" name="name" class="form-control" value="<?= e($plan['name']) ?>" required></div>
      <div class="mb-3"><label class="form-label fw-semibold">Description</label>
        <textarea name="description" class="form-control" rows="2"><?= e($plan['description']) ?></textarea></div>
      <div class="row g-3 mb-3">
        <div class="col-md-6"><label class="form-label fw-semibold">Week Start</label>
          <input type="date" name="week_start" class="form-control" value="<?= e($plan['week_start']) ?>"></div>
        <div class="col-md-6"><label class="form-label fw-semibold">Week End</label>
          <input type="date" name="week_end" class="form-control" value="<?= e($plan['week_end']) ?>"></div>
      </div>
      <div class="mb-4"><label class="form-label fw-semibold">Status</label>
        <select name="status" class="form-select">
          <?php foreach(['draft'=>'Draft','active'=>'Active','completed'=>'Completed'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= $plan['status']===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4 fw-semibold"><i class="bi bi-check-lg me-2"></i>Update Plan</button>
        <a href="<?= url('mealplans/view/'.$plan['id']) ?>" class="btn btn-outline-secondary px-4">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_footer.php'; ?>
