<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>
<div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);max-width:640px;">
  <div class="card-header bg-white border-0 px-4 pt-4 pb-0"><h5 class="fw-bold">Add Category</h5></div>
  <div class="card-body px-4 pb-4">
    <form method="POST" action="<?= url('categories/store') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label fw-semibold">Name *</label>
        <input type="text" name="name" class="form-control" value="<?= old('name') ?>" required placeholder="e.g. Breakfast" oninput="autoSlug(this.value)"></div>
      <div class="mb-3"><label class="form-label fw-semibold">Slug *</label>
        <input type="text" name="slug" id="slugField" class="form-control" value="<?= old('slug') ?>" required placeholder="e.g. breakfast">
        <div class="text-muted small mt-1">URL-friendly identifier. Auto-generated from name.</div></div>
      <div class="mb-3"><label class="form-label fw-semibold">Description</label>
        <textarea name="description" class="form-control" rows="2" placeholder="Brief description…"><?= old('description') ?></textarea></div>
      <div class="mb-3"><label class="form-label fw-semibold">Image <span class="text-muted fw-normal">(optional)</span></label>
        <input type="file" name="image" class="form-control" accept="image/*"></div>
      <div class="mb-4"><label class="form-label fw-semibold">Status</label>
        <select name="status" class="form-select">
          <option value="active">Active</option><option value="inactive">Inactive</option>
        </select></div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success px-4 fw-semibold"><i class="bi bi-check-lg me-2"></i>Save Category</button>
        <a href="<?= url('categories/index') ?>" class="btn btn-outline-secondary px-4">Cancel</a>
      </div>
    </form>
  </div>
</div>
<script>function autoSlug(v){const s=document.getElementById('slugField');if(!s.dataset.manual){s.value=v.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');}}</script>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
