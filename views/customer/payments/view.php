<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_header.php'; ?>
<div class="card border-0 mx-auto" style="max-width:560px;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08);">
  <div class="card-header text-center py-4" style="background:<?= $payment['status']==='success'?'linear-gradient(135deg,#2d6a4f,#52b788)':'#f8d7da' ?>;border-radius:16px 16px 0 0;color:<?= $payment['status']==='success'?'#fff':'#721c24' ?>;">
    <div style="font-size:2.5rem;"><?= $payment['status']==='success'?'✅':'❌' ?></div>
    <h5 class="fw-bold mt-2 mb-0"><?= ucfirst($payment['status']) ?></h5>
  </div>
  <div class="card-body p-4">
    <table class="table table-sm table-borderless mb-0">
      <tr><th class="text-muted fw-normal w-40">Reference</th><td><code class="small"><?= e($payment['transaction_ref']) ?></code></td></tr>
      <tr><th class="text-muted fw-normal">Amount</th><td class="fw-bold fs-5"><?= format_currency((float)$payment['amount']) ?></td></tr>
      <tr><th class="text-muted fw-normal">Provider</th><td><?= e($payment['provider']) ?></td></tr>
      <tr><th class="text-muted fw-normal">Phone</th><td><?= e($payment['phone_number']) ?></td></tr>
      <tr><th class="text-muted fw-normal">Date</th><td><?= $payment['paid_at'] ? format_date($payment['paid_at'],'d F Y H:i') : format_date($payment['created_at'],'d F Y H:i') ?></td></tr>
    </table>
    <div class="d-flex gap-2 mt-4">
      <a href="<?= url('payments/index') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
      <?php if($payment['status']==='failed'): ?>
      <a href="<?= url('subscriptions/index') ?>" class="btn btn-success flex-fill"><i class="bi bi-arrow-repeat me-2"></i>Try Again</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_footer.php'; ?>
