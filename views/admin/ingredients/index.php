<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>
<div class="alert alert-info">
  <h5 class="fw-bold"><i class="bi bi-info-circle me-2"></i>Ingredients are managed per-recipe</h5>
  <p class="mb-2">Ingredients are added and edited directly within each recipe's create/edit form.</p>
  <a href="<?= url('recipes/adminIndex') ?>" class="btn btn-success btn-sm">Go to Recipes</a>
</div>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
