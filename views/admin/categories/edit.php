<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>
<div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);max-width:640px;">
  <div class="card-header bg-white border-0 px-4 pt-4 pb-0"><h5 class="fw-bold">Edit Category</h5></div>
  <div class="card-body px-4 pb-4">
    <form method="POST" action="<?= url('categories/update/'.$category['id']) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label fw-semibold">Name *</label>
        <input type="text" name="name" class="form-control" value="<?= e($category['name']) ?>" required></div>
      <div class="mb-3"><label class="form-label fw-semibold">Slug *</label>
        <input type="text" name="slug" class="form-control" value="<?= e($category['slug']) ?>" required></div>
      <div class="mb-3"><label class="form-label fw-semibold">Description</label>
        <textarea name="description" class="form-control" rows="2"><?= e($category['description']) ?></textarea></div>
      <div class="mb-3"><label class="form-label fw-semibold">Image</label>
        <?php if($category['image']): ?><div class="mb-2"><img src="<?= recipe_img_url($category['image']) ?>" style="height:60px;border-radius:6px;" alt=""></div><?php endif; ?>
        <input type="file" name="image" class="form-control" accept="image/*">
        <div class="text-muted small">Leave blank to keep current image.</div></div>
      <div class="mb-4"><label class="form-label fw-semibold">Status</label>
        <select name="status" class="form-select">
          <option value="active" <?= $category['status']==='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= $category['status']==='inactive'?'selected':'' ?>>Inactive</option>
        </select></div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4 fw-semibold"><i class="bi bi-check-lg me-2"></i>Update</button>
        <a href="<?= url('categories/index') ?>" class="btn btn-outline-secondary px-4">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
