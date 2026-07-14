<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">📥 Download History</h4>
  <?php if(!$permissions['can_download']): ?>
  <a href="<?= url('subscriptions/index') ?>" class="btn btn-success btn-sm">Upgrade to Download</a>
  <?php endif; ?>
</div>

<?php if(!$permissions['can_download']): ?>
<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>PDF downloads require a <strong>Basic</strong> or <strong>Premium</strong> subscription.</div>
<?php endif; ?>

<?php if(empty($rows)): ?>
<div class="text-center py-5">
  <div style="font-size:3rem;">📄</div>
  <h5 class="text-muted mt-3">No downloads yet</h5>
  <p class="text-muted small">Download any recipe PDF to see it here.</p>
  <a href="<?= url('recipes/index') ?>" class="btn btn-outline-success">Browse Recipes</a>
</div>
<?php else: ?>
<div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Recipe</th><th>Category</th><th>Downloaded</th><th></th></tr></thead>
        <tbody>
          <?php foreach($rows as $d): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <img src="<?= recipe_img_url($d['recipe_image']) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:6px;" alt="">
                <span class="fw-semibold small"><?= e(truncate($d['recipe_title'],40)) ?></span>
              </div>
            </td>
            <td class="small text-muted"><?= e($d['category_name']) ?></td>
            <td class="small text-muted"><?= format_date($d['downloaded_at'],'d M Y H:i') ?></td>
            <td>
              <a href="<?= url('recipes/download/'.$d['recipe_id']) ?>" class="btn btn-sm btn-outline-success" target="_blank">
                <i class="bi bi-download me-1"></i>Re-download
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if($pager['total_pages']>1): ?><div class="card-footer bg-white"><?= render_pagination($pager) ?></div><?php endif; ?>
</div>
<?php endif; ?>

<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_footer.php'; ?>
