<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_header.php'; ?>

<!-- Search + Filters -->
<form method="GET" action="<?= url('recipes/index') ?>" class="card border-0 mb-4" style="border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.05);">
  <div class="card-body">
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="🔍 Search recipes…"
               value="<?= e($filters['search'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <select name="category" class="form-select">
          <option value="">All Categories</option>
          <?php foreach($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= ($filters['category_id']??0)==$cat['id']?'selected':'' ?>>
              <?= e($cat['name']) ?> (<?= $cat['recipe_count'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1"><button class="btn btn-success w-100"><i class="bi bi-search"></i></button></div>
    </div>
  </div>
</form>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-bold mb-0">Recipes <span class="text-muted fw-normal fs-6">(<?= number_format($pager['total_items']) ?> found)</span></h5>
  <a href="<?= url('recipes/search') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-search me-1"></i>Advanced Search</a>
</div>

<?php if(!$permissions['can_access_premium']): ?>
<div class="alert alert-info d-flex justify-content-between align-items-center py-2 mb-3">
  <span class="small"><i class="bi bi-star me-2"></i>Premium recipes are hidden. <strong>Upgrade</strong> to Premium plan to unlock all recipes.</span>
  <a href="<?= url('subscriptions/index') ?>" class="btn btn-sm btn-success">Upgrade</a>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <?php foreach($rows as $r): ?>
  <div class="col-sm-6 col-lg-4 col-xl-3">
    <div class="card card-recipe h-100">
      <div class="position-relative">
        <img src="<?= recipe_img_url($r['image']) ?>" class="card-img-top" alt="<?= e($r['title']) ?>">
        <!-- Favourite button -->
        <form method="POST" action="<?= url('favourites/toggle') ?>" class="position-absolute top-0 end-0 m-2 fav-form">
          <?= csrf_field() ?>
          <input type="hidden" name="recipe_id" value="<?= $r['id'] ?>">
          <button type="submit" class="btn btn-sm btn-light rounded-circle shadow-sm fav-btn"
                  style="width:34px;height:34px;padding:0;">
            <i class="bi <?= in_array($r['id'],$favIds)?'bi-heart-fill text-danger':'bi-heart text-muted' ?>"></i>
          </button>
        </form>
      </div>
      <div class="card-body pb-1">
        <div class="d-flex gap-1 mb-1 flex-wrap">
          <?php if($r['is_premium']): ?><span class="premium-badge">⭐ Premium</span><?php endif; ?>
          <?= difficulty_badge($r['difficulty']) ?>
        </div>
        <h6 class="fw-bold mb-1 small"><?= e(truncate($r['title'],38)) ?></h6>
        <p class="text-muted small mb-2"><?= truncate(e($r['description']),70) ?></p>
        <div class="d-flex gap-3 text-muted small">
          <span><i class="bi bi-clock me-1"></i><?= format_duration((int)$r['prep_time']+(int)$r['cook_time']) ?></span>
          <span><i class="bi bi-people me-1"></i><?= e($r['servings']) ?></span>
          <?php if($r['calories']): ?><span><i class="bi bi-fire me-1"></i><?= e($r['calories']) ?> kcal</span><?php endif; ?>
        </div>
      </div>
      <div class="card-footer bg-white border-0 pt-0 pb-3 px-3 d-flex gap-1">
        <a href="<?= url('recipes/view/'.$r['id']) ?>" class="btn btn-sm btn-success flex-fill">View Recipe</a>
        <?php if($permissions['can_download']): ?>
        <a href="<?= url('recipes/download/'.$r['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Download PDF" target="_blank">
          <i class="bi bi-download"></i>
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if(empty($rows)): ?>
  <div class="col-12 text-center py-5">
    <div style="font-size:3rem;">🍽️</div>
    <h5 class="text-muted mt-2">No recipes found</h5>
    <p class="text-muted small">Try adjusting your filters.</p>
  </div>
  <?php endif; ?>
</div>

<?= render_pagination($pager) ?>

<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_footer.php'; ?>
