<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">💳 Payment History</h4>
  <a href="<?= url('subscriptions/index') ?>" class="btn btn-success btn-sm">Subscribe / Renew</a>
</div>

<?php if(empty($rows)): ?>
<div class="text-center py-5">
  <div style="font-size:3rem;">💳</div>
  <h5 class="text-muted mt-3">No payments yet</h5>
  <p class="text-muted small">Subscribe to a plan to see your payment history here.</p>
  <a href="<?= url('subscriptions/index') ?>" class="btn btn-success px-4">View Plans</a>
</div>
<?php else: ?>
<div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Reference</th><th>Plan</th><th>Amount</th><th>Provider</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
          <?php foreach($rows as $p): ?>
          <tr>
            <td><code class="small"><?= e(substr($p['transaction_ref'],0,18)) ?>…</code></td>
            <td class="small"><?= e($p['plan_name'] ?? '—') ?></td>
            <td class="fw-semibold small"><?= format_currency((float)$p['amount']) ?></td>
            <td class="small"><?= e($p['provider']) ?></td>
            <td><?= status_badge($p['status']) ?></td>
            <td class="small text-muted"><?= format_date($p['created_at'],'d M Y') ?></td>
            <td><a href="<?= url('payments/view/'.$p['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-receipt"></i></a></td>
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
