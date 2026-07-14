<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>
<div class="row g-4">
  <div class="col-md-6">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-header bg-white border-0 fw-bold p-4 pb-0">Payment Details</div>
      <div class="card-body p-4">
        <table class="table table-sm table-borderless mb-0">
          <tr><th class="text-muted fw-normal w-40">Reference</th><td><code><?= e($payment['transaction_ref']) ?></code></td></tr>
          <tr><th class="text-muted fw-normal">Amount</th><td class="fw-bold fs-5"><?= format_currency((float)$payment['amount']) ?></td></tr>
          <tr><th class="text-muted fw-normal">Status</th><td><?= status_badge($payment['status']) ?></td></tr>
          <tr><th class="text-muted fw-normal">Provider</th><td><?= e($payment['provider']) ?></td></tr>
          <tr><th class="text-muted fw-normal">Phone</th><td><?= e($payment['phone_number']) ?></td></tr>
          <tr><th class="text-muted fw-normal">Method</th><td><?= e(ucfirst(str_replace('_',' ',$payment['payment_method']))) ?></td></tr>
          <tr><th class="text-muted fw-normal">Paid At</th><td><?= $payment['paid_at'] ? format_date($payment['paid_at'],'d M Y H:i') : '—' ?></td></tr>
          <tr><th class="text-muted fw-normal">Created</th><td><?= format_date($payment['created_at'],'d M Y H:i') ?></td></tr>
        </table>
        <?php if($payment['status']==='success'): ?>
        <form method="POST" action="<?= url('payments/refund/'.$payment['id']) ?>" class="mt-3" onsubmit="return confirm('Mark this payment as refunded?')">
          <?= csrf_field() ?>
          <button class="btn btn-outline-warning btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Mark Refunded</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
      <div class="card-header bg-white border-0 fw-bold p-4 pb-0">Gateway Response</div>
      <div class="card-body p-4">
        <pre class="bg-dark text-success p-3 rounded small" style="font-size:.78rem;max-height:300px;overflow-y:auto;"><?= e(json_encode($gateway, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
      </div>
    </div>
  </div>
</div>
<div class="mt-3"><a href="<?= url('payments/adminIndex') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back to Payments</a></div>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
