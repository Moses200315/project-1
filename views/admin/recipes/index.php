<?php require_once VIEWS_PATH . DS . "layouts" . DS . "admin_header.php"; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
  <?php foreach (
      [
          ["Published", $stats["published"], "bi-check-circle", "success"],
          ["Draft", $stats["draft"], "bi-pencil", "warning"],
          ["Premium", $stats["premium"], "bi-star", "info"],
          [
              "Total Views",
              number_format($stats["total_views"]),
              "bi-eye",
              "primary",
          ],
      ]
      as [$l, $v, $i, $c]
  ): ?>
  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 stat-card">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon bg-<?= $c ?> bg-opacity-10"><i class="bi <?= $i ?> text-<?= $c ?> fs-5"></i></div>
        <div><div class="text-muted small"><?= $l ?></div><div class="fw-bold fs-5"><?= $v ?></div></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filters + Add -->
<div class="card border-0 mb-4" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
  <div class="card-body">
    <form method="GET" action="<?= url(
        "recipes/adminIndex",
    ) ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="🔍 Search recipes…"
               value="<?= e($search) ?>">
      </div>
      <div class="col-md-3">
        <select name="category" class="form-select">
          <option value="">All Categories</option>
          <?php foreach ($categories as $id => $name): ?>
            <option value="<?= $id ?>" <?= $category == $id
    ? "selected"
    : "" ?>><?= e($name) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <select name="status" class="form-select">
          <option value="">All Status</option>
          <?php foreach (
              [
                  "published" => "Published",
                  "draft" => "Draft",
                  "archived" => "Archived",
              ]
              as $v => $l
          ): ?>
            <option value="<?= $v ?>" <?= $status === $v
    ? "selected"
    : "" ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1"><button class="btn btn-outline-secondary w-100">Filter</button></div>
      <div class="col-md-2">
        <a href="<?= url("recipes/create") ?>" class="btn btn-success w-100">
          <i class="bi bi-plus-lg me-1"></i>Add Recipe
        </a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr><th>#</th><th>Recipe</th><th>Category</th><th>Diff.</th><th>Views</th><th>Status</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td class="text-muted small"><?= e($r["id"]) ?></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <img src="<?= recipe_img_url(
                    $r["image"],
                ) ?>" style="width:38px;height:38px;object-fit:cover;border-radius:8px;" alt="">
                <div>
                  <div class="fw-semibold small"><?= e(
                      truncate($r["title"], 40),
                  ) ?></div>
                  <?php if (
                      $r["is_premium"]
                  ): ?><span class="badge" style="background:#f4a261;font-size:.6rem;">⭐ Premium</span><?php endif; ?>
                </div>
              </div>
            </td>
            <td class="small text-muted"><?= e($r["category_name"]) ?></td>
            <td><?= difficulty_badge($r["difficulty"]) ?></td>
            <td class="small"><?= number_format($r["views"]) ?></td>
            <td><?= status_badge($r["status"]) ?></td>
            <td class="text-end">
              <a href="<?= url(
                  "recipes/show/" . $r["id"],
              ) ?>" class="btn btn-sm btn-outline-info" target="_blank" title="Preview">
                <i class="bi bi-eye"></i>
              </a>
              <a href="<?= url(
                  "recipes/edit/" . $r["id"],
              ) ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                <i class="bi bi-pencil"></i>
              </a>
              <form method="POST" action="<?= url(
                  "recipes/delete/" . $r["id"],
              ) ?>" class="d-inline"
                    onsubmit="return confirm('Archive this recipe?')">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger" title="Archive"><i class="bi bi-archive"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($rows)): ?>
          <tr><td colspan="7" class="text-center py-4 text-muted">No recipes found. <a href="<?= url(
              "recipes/create",
          ) ?>">Add the first one.</a></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($pager["total_pages"] > 1): ?>
  <div class="card-footer bg-white"><?= render_pagination($pager) ?></div>
  <?php endif; ?>
</div>

<?php require_once VIEWS_PATH . DS . "layouts" . DS . "admin_footer.php"; ?>
