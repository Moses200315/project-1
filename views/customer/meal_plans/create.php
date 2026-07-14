<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_header.php'; ?>

<div class="card border-0 mx-auto" style="max-width:600px;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
  <div class="card-header bg-white border-0 fw-bold p-4 pb-0">
    <h5 class="mb-0"><i class="bi bi-calendar-plus me-2 text-success"></i>Create New Meal Plan</h5>
  </div>
  <div class="card-body p-4">
    <form method="POST" action="<?= url('mealplans/store') ?>">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label class="form-label fw-semibold">Plan Name *</label>
        <input type="text" name="name" class="form-control" value="<?= old('name','My Week Plan') ?>"
               placeholder="e.g. Healthy Week 1" required>
      </div>

      <div class="mb-3">
        <label class="form-label fw-semibold">Description</label>
        <textarea name="description" class="form-control" rows="2"
                  placeholder="What's the goal of this plan?"><?= old('description') ?></textarea>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Week Start *</label>
          <input type="date" name="week_start" class="form-control" value="<?= old('week_start',$weekStart) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Week End *</label>
          <input type="date" name="week_end" class="form-control" value="<?= old('week_end',$weekEnd) ?>" required>
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label fw-semibold">Status</label>
        <select name="status" class="form-select">
          <option value="draft">Draft – build it first</option>
          <option value="active">Active – start using it</option>
        </select>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success px-4 fw-semibold">
          <i class="bi bi-check-lg me-2"></i>Create Plan
        </button>
        <a href="<?= url('mealplans/index') ?>" class="btn btn-outline-secondary px-4">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_footer.php'; ?>
