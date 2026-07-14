<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Notifications <span class="badge bg-danger ms-2"><?= $unreadCount ?> unread</span></h4>
  <?php if($unreadCount > 0): ?>
  <form method="POST" action="<?= url('notifications/markAllRead') ?>">
    <?= csrf_field() ?>
    <button class="btn btn-sm btn-outline-success">Mark All Read</button>
  </form>
  <?php endif; ?>
</div>

<?php if(empty($notifications)): ?>
<div class="text-center py-5">
  <div style="font-size:3rem;">🔔</div>
  <h5 class="text-muted mt-3">No notifications yet</h5>
  <p class="text-muted small">Activity updates will appear here.</p>
</div>
<?php else: ?>
<div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
  <?php foreach($notifications as $n): ?>
  <?php $icons=['info'=>'bi-info-circle text-info','success'=>'bi-check-circle-fill text-success','warning'=>'bi-exclamation-triangle text-warning','error'=>'bi-x-circle text-danger']; ?>
  <div class="d-flex gap-3 p-3 border-bottom <?= !$n['is_read']?'bg-light':'' ?>" style="<?= !$n['is_read']?'border-left:3px solid #2d6a4f!important;':'' ?>">
    <div class="flex-shrink-0 mt-1"><i class="bi <?= $icons[$n['type']] ?? 'bi-bell' ?> fs-5"></i></div>
    <div class="flex-grow-1">
      <div class="d-flex justify-content-between align-items-start">
        <div class="fw-semibold small"><?= e($n['title']) ?></div>
        <span class="text-muted" style="font-size:.72rem;white-space:nowrap;"><?= time_ago($n['created_at']) ?></span>
      </div>
      <p class="text-muted small mb-1"><?= e($n['message']) ?></p>
      <div class="d-flex gap-2">
        <?php if($n['action_url']): ?>
        <a href="<?= e($n['action_url']) ?>" class="btn btn-xs btn-outline-success" style="font-size:.72rem;padding:.15rem .5rem;border-radius:4px;">View</a>
        <?php endif; ?>
        <?php if(!$n['is_read']): ?>
        <form method="POST" action="<?= url('notifications/markRead') ?>" class="d-inline">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $n['id'] ?>">
          <button class="btn" style="font-size:.72rem;padding:.15rem .5rem;border:1px solid #dee2e6;border-radius:4px;">Mark Read</button>
        </form>
        <?php endif; ?>
        <form method="POST" action="<?= url('notifications/delete/'.$n['id']) ?>" class="d-inline" onsubmit="return confirm('Delete?')">
          <?= csrf_field() ?>
          <button class="btn" style="font-size:.72rem;padding:.15rem .5rem;border:1px solid #fee2e2;color:#dc3545;border-radius:4px;"><i class="bi bi-trash"></i></button>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'customer_footer.php'; ?>
