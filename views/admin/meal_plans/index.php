<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>
<div class="alert alert-info">
  <h5 class="fw-bold"><i class="bi bi-info-circle me-2"></i>Meal Plans are customer-managed</h5>
  <p class="mb-2">Customers create and manage their own weekly meal plans from the customer dashboard.
     You can view a customer's meal plans from their profile page.</p>
  <a href="<?= url('admin/users') ?>" class="btn btn-success btn-sm">View Customers</a>
</div>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
