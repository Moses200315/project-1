<?php require_once VIEWS_PATH . DS . "layouts" . DS . "customer_header.php"; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">❤️ My Favourites <span class="text-muted fw-normal fs-6">(<?= number_format(
      $pager["total_items"],
  ) ?>)</span></h4>
  <a href="<?= url(
      "recipes/index",
  ) ?>" class="btn btn-outline-success btn-sm">Browse Recipes</a>
</div>

<?php if (empty($rows)): ?>
<div class="text-center py-5">
  <div style="font-size:3rem;">🤍</div>
  <h5 class="text-muted mt-3">No favourites yet</h5>
  <p class="text-muted small">Tap the heart icon on any recipe to save it here.</p>
  <a href="<?= url(
      "recipes/index",
  ) ?>" class="btn btn-success px-4">Discover Recipes</a>
</div>
<?php else: ?>
<div class="row g-3 mb-4">
  <?php foreach ($rows as $r): ?>
  <div class="col-sm-6 col-lg-4 col-xl-3">
    <div class="card card-recipe h-100">
      <img src="<?= recipe_img_url(
          $r["image"],
      ) ?>" class="card-img-top" alt="<?= e($r["title"]) ?>">
      <div class="card-body pb-1">
        <?php if (
            $r["is_premium"]
        ): ?><span class="premium-badge">⭐ Premium</span><?php endif; ?>
        <h6 class="fw-bold mt-1 mb-1 small"><?= e(
            truncate($r["title"], 38),
        ) ?></h6>
        <div class="d-flex gap-2 text-muted small mb-1">
          <span><?= e($r["category_name"]) ?></span>·
          <span><?= format_duration(
              (int) $r["prep_time"] + (int) $r["cook_time"],
          ) ?></span>
        </div>
        <div class="text-muted" style="font-size:.72rem;">Saved <?= time_ago(
            $r["saved_at"],
        ) ?></div>
      </div>
      <div class="card-footer bg-white border-0 pt-0 pb-3 px-3 d-flex gap-1">
        <a href="<?= url(
            "recipes/view/" . $r["id"],
        ) ?>" class="btn btn-sm btn-success flex-fill">View</a>
        <form method="POST" action="<?= url(
            "favourites/remove/" . $r["id"],
        ) ?>" class="d-inline" onsubmit="return confirm('Remove from favourites?')">
          <?= csrf_field() ?>
          <button class="btn btn-sm btn-outline-danger" title="Remove"><i class="bi bi-heart-slash"></i></button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?= render_pagination($pager) ?>
<?php endif; ?>

<?php require_once VIEWS_PATH . DS . "layouts" . DS . "customer_footer.php"; ?>
