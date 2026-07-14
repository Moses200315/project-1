<?php require_once VIEWS_PATH . DS . "layouts" . DS . "customer_header.php"; ?>

<!-- Subscription Alert -->
<?php if (!$permissions["has_subscription"]): ?>
<div class="subscription-alert mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <div class="fw-bold">🎁 You're on the Free Plan</div>
    <div class="small opacity-75">Subscribe to unlock unlimited recipes, meal planning, PDF downloads, and more.</div>
  </div>
  <a href="<?= url("subscriptions/index") ?>" class="btn btn-warning fw-semibold btn-sm px-4">Subscribe Now</a>
</div>
<?php elseif ($permissions["days_remaining"] <= 5 && $permissions["days_remaining"] > 0): ?>
<div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <span><i class="bi bi-exclamation-triangle me-2"></i>Your <strong><?= e($permissions["plan_name"]) ?></strong> plan expires in <strong><?= $permissions["days_remaining"] ?> day(s)</strong>.</span>
  <a href="<?= url("subscriptions/index") ?>" class="btn btn-warning btn-sm fw-semibold">Renew</a>
</div>
<?php else: ?>
<!-- Active Subscription Status -->
<div class="card border-0 mb-4" style="border-radius:14px;box-shadow:0 2px 10px rgba(45,106,79,.08);border-left:4px solid #2d6a4f!important;">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h6 class="fw-bold mb-1">📋 <?= e($permissions["plan_name"]) ?> Plan</h6>
        <div class="text-muted small">
          <?php if ($permissions["ends_at"]): ?>
            Valid until: <strong><?= format_date($permissions["ends_at"], "d M Y") ?></strong>
            (<?= $permissions["days_remaining"] ?> days remaining)
          <?php else: ?>
            Active subscription
          <?php endif; ?>
        </div>
      </div>
      <div class="text-end">
        <div class="small text-muted mb-1">Features included:</div>
        <div class="d-flex gap-2 flex-wrap">
          <?php if ($permissions["can_access_premium"]): ?>
            <span class="badge bg-success">Premium Recipes</span>
          <?php endif; ?>
          <?php if ($permissions["can_download"]): ?>
            <span class="badge bg-info">PDF Downloads</span>
          <?php endif; ?>
          <span class="badge bg-primary">Meal Planning</span>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Stats Row -->
<div class="row g-3 mb-4">
  <?php foreach (
      [
          [
              "Favourite Recipes",
              $favCount,
              "bi-heart-fill",
              "danger",
              url("favourites/index"),
          ],
          [
              "Meal Plans",
              count($mealPlans),
              "bi-calendar3",
              "success",
              url("mealplans/index"),
          ],
          [
              "Downloads",
              $downloadCount,
              "bi-download",
              "info",
              url("profile/downloads"),
          ],
          [
              "Notifications",
              $unreadCount . " unread",
              "bi-bell-fill",
              "warning",
              url("notifications/index"),
          ],
      ]
      as [$l, $v, $i, $c, $href]
  ): ?>
  <div class="col-sm-6 col-xl-3">
    <a href="<?= $href ?>" class="text-decoration-none">
      <div class="card border-0 h-100" style="border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,.05);">
        <div class="card-body d-flex align-items-center gap-3">
          <div style="width:48px;height:48px;border-radius:12px;" class="bg-<?= $c ?> bg-opacity-10 d-flex align-items-center justify-content-center">
            <i class="bi <?= $i ?> text-<?= $c ?> fs-5"></i>
          </div>
          <div><div class="text-muted small"><?= $l ?></div><div class="fw-bold fs-5 text-dark"><?= $v ?></div></div>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Active Meal Plan -->
<?php if ($activePlan): ?>
<div class="card border-0 mb-4" style="border-radius:14px;box-shadow:0 2px 10px rgba(45,106,79,.08);border-left:4px solid #2d6a4f!important;">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div><h6 class="fw-bold mb-0"><?= e(
          $activePlan["name"],
      ) ?> <span class="badge bg-success ms-1">Active</span></h6>
        <div class="text-muted small"><?= format_date(
            $activePlan["week_start"],
            "d M",
        ) ?> – <?= format_date($activePlan["week_end"], "d M Y") ?></div>
      </div>
      <a href="<?= url(
          "mealplans/show/" . $activePlan["id"],
      ) ?>" class="btn btn-outline-success btn-sm">View Full Plan</a>
    </div>
    <div class="row g-2">
      <?php foreach (["Monday", "Tuesday", "Wednesday"] as $day): ?>
      <div class="col-md-4">
        <div class="border rounded p-2" style="border-radius:10px!important;">
          <div class="fw-semibold small text-success mb-1"><?= $day ?></div>
          <?php
          $daySlots = array_filter(
              $activePlan["slots"] ?? [],
              fn($s) => $s["day_of_week"] === $day,
          );
          if (
              empty($daySlots)
          ): ?><div class="text-muted small">No meals planned</div>
          <?php else:foreach ($daySlots as $slot): ?>
            <div class="small"><span class="badge bg-light text-dark me-1"><?= ucfirst(
                $slot["meal_type"],
            ) ?></span><?= e(truncate($slot["recipe_title"], 25)) ?></div>
          <?php endforeach;endif;
          ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Recent Recipes -->
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-bold mb-0">✨ Recent Recipes</h5>
  <a href="<?= url(
      "recipes/index",
  ) ?>" class="btn btn-sm btn-outline-success">Browse All</a>
</div>
<div class="row g-3 mb-4">
  <?php foreach ($featuredRecipes as $r): ?>
  <div class="col-sm-6 col-xl-3">
    <div class="card card-recipe h-100">
      <img src="<?= recipe_img_url(
          $r["image"],
      ) ?>" class="card-img-top" alt="<?= e($r["title"]) ?>">
      <div class="card-body pb-1">
        <?php if (
            $r["is_premium"]
        ): ?><span class="premium-badge">⭐ Premium</span><?php endif; ?>
        <h6 class="fw-bold mt-1 mb-1 small"><?= e(
            truncate($r["title"], 40),
        ) ?></h6>
        <div class="d-flex gap-2 text-muted small">
          <span><i class="bi bi-clock me-1"></i><?= format_duration(
              (int) $r["prep_time"] + (int) $r["cook_time"],
          ) ?></span>
          <?= difficulty_badge($r["difficulty"]) ?>
        </div>
      </div>
      <div class="card-footer bg-white border-0 pt-0 pb-2 px-3">
        <a href="<?= url(
            "recipes/show/" . $r["id"],
        ) ?>" class="btn btn-sm btn-success w-100">View Recipe</a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (
      empty($featuredRecipes)
  ): ?><div class="col-12"><p class="text-muted">No featured recipes yet.</p></div><?php endif; ?>
</div>

<!-- Favourites Preview -->
<?php if (!empty($favourites)): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="fw-bold mb-0">❤️ Your Favourites</h5>
  <a href="<?= url(
      "favourites/index",
  ) ?>" class="btn btn-sm btn-outline-danger">View All</a>
</div>
<div class="row g-3 mb-4">
  <?php foreach ($favourites as $r): ?>
  <div class="col-sm-6 col-xl-3">
    <div class="card card-recipe h-100">
      <img src="<?= recipe_img_url(
          $r["image"],
      ) ?>" class="card-img-top" alt="<?= e($r["title"]) ?>">
      <div class="card-body pb-2">
        <h6 class="fw-bold small mb-1"><?= e(truncate($r["title"], 35)) ?></h6>
        <span class="text-muted small"><?= e($r["category_name"]) ?></span>
      </div>
      <div class="card-footer bg-white border-0 pt-0 pb-2 px-3">
        <a href="<?= url(
            "recipes/show/" . $r["id"],
        ) ?>" class="btn btn-sm btn-outline-success w-100">View</a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once VIEWS_PATH . DS . "layouts" . DS . "customer_footer.php"; ?>
