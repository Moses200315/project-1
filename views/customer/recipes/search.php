<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_header.php'; ?>

<div class="card border-0 mb-4" style="border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.05);">
  <div class="card-body">
    <form method="GET" action="<?= url('recipes/search') ?>" class="d-flex gap-2">
      <input type="text" name="q" class="form-control form-control-lg" value="<?= e($query) ?>"
             placeholder="Search recipes by name, description…" autofocus>
      <button class="btn btn-success btn-lg px-4 fw-semibold">Search</button>
    </form>
  </div>
</div>

<?php if($query !== ''): ?>
<div class="mb-3">
  <h5 class="fw-bold">Results for "<em><?= e($query) ?></em>" <span class="text-muted fw-normal fs-6">(<?= number_format($pager['total_items']) ?> found)</span></h5>
</div>
<?php endif; ?>

<div class="row g-3">
  <?php foreach($rows as $r): ?>
  <div class="col-sm-6 col-lg-4">
    <div class="card card-recipe h-100">
      <img src="<?= recipe_img_url($r['image']) ?>" class="card-img-top" alt="<?= e($r['title']) ?>">
      <div class="card-body pb-1">
        <?php if($r['is_premium']): ?><span class="premium-badge">⭐ Premium</span><?php endif; ?>
        <h6 class="fw-bold mt-1 mb-1 small"><?= e(truncate($r['title'],42)) ?></h6>
        <p class="text-muted small mb-2"><?= truncate(e($r['description']),90) ?></p>
        <div class="d-flex gap-2 text-muted small">
          <span><i class="bi bi-clock me-1"></i><?= format_duration((int)$r['prep_time']+(int)$r['cook_time']) ?></span>
          <?= difficulty_badge($r['difficulty']) ?>
        </div>
      </div>
      <div class="card-footer bg-white border-0 pt-0 pb-3 px-3">
        <a href="<?= url('recipes/view/'.$r['id']) ?>" class="btn btn-sm btn-success w-100">View Recipe</a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if($query !== '' && empty($rows)): ?>
  <div class="col-12 text-center py-5">
    <div style="font-size:3rem;">🔍</div>
    <h5 class="text-muted mt-2">No results found</h5>
    <p class="text-muted small">Try different keywords or browse by category.</p>
    <a href="<?= url('recipes/index') ?>" class="btn btn-outline-success">Browse All Recipes</a>
  </div>
  <?php endif; ?>
</div>

<?= render_pagination($pager) ?>

<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_footer.php'; ?>
