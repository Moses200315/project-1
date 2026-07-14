<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Categories</h4><p class="text-muted small mb-0"><?= count($categories) ?> categories total</p></div>
  <a href="<?= url('categories/create') ?>" class="btn btn-success"><i class="bi bi-plus-lg me-1"></i>Add Category</a>
</div>

<div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Category</th><th>Slug</th><th>Published</th><th>Total</th><th>Status</th><th>Created By</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php foreach($categories as $c): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php if($c['image']): ?><img src="<?= recipe_img_url($c['image']) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:6px;" alt=""><?php endif; ?>
                <div class="fw-semibold small"><?= e($c['name']) ?></div>
              </div>
            </td>
            <td><code class="small"><?= e($c['slug']) ?></code></td>
            <td><span class="badge bg-success"><?= (int)$c['published_count'] ?></span></td>
            <td><span class="badge bg-secondary"><?= (int)$c['total_count'] ?></span></td>
            <td><?= status_badge($c['status']) ?></td>
            <td class="small text-muted"><?= e($c['creator_first'].' '.$c['creator_last']) ?></td>
            <td class="text-end">
              <a href="<?= url('categories/edit/'.$c['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <form method="POST" action="<?= url('categories/delete/'.$c['id']) ?>" class="d-inline"
                    onsubmit="return confirm('Delete this category? This will fail if recipes are assigned to it.')">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($categories)): ?><tr><td colspan="7" class="text-center py-4 text-muted">No categories yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
