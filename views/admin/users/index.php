<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_header.php'; ?>

<div class="row g-3 mb-4">
  <?php foreach([['Total',$stats['total'],'primary'],['Active',$stats['active'],'success'],['Inactive',$stats['inactive'],'warning'],['Banned',$stats['banned'],'danger']] as [$l,$v,$c]): ?>
  <div class="col-sm-6 col-xl-3">
    <div class="card border-0 stat-card"><div class="card-body d-flex align-items-center gap-3">
      <div class="stat-icon bg-<?= $c ?> bg-opacity-10"><i class="bi bi-person-fill text-<?= $c ?> fs-5"></i></div>
      <div><div class="text-muted small"><?= $l ?> Customers</div><div class="fw-bold fs-4"><?= number_format($v) ?></div></div>
    </div></div>
  </div>
  <?php endforeach; ?>
</div>

<div class="card border-0 mb-3" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
  <div class="card-body">
    <form method="GET" action="<?= url('admin/users') ?>" class="row g-2 align-items-end">
      <div class="col-md-6"><input type="text" name="search" class="form-control" placeholder="🔍 Search by name or email…" value="<?= e($search) ?>"></div>
      <div class="col-md-3">
        <select name="status" class="form-select">
          <option value="">All Statuses</option>
          <?php foreach(['active','inactive','banned'] as $s): ?>
            <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filter</button></div>
      <div class="col-md-1"><a href="<?= url('admin/users') ?>" class="btn btn-outline-danger w-100" title="Clear"><i class="bi bi-x-lg"></i></a></div>
    </form>
  </div>
</div>

<div class="card border-0" style="border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Customer</th><th>Phone</th><th>Plan</th><th>Status</th><th>Joined</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          <?php foreach($rows as $u): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <img src="<?= avatar_url($u['avatar']) ?>" style="width:36px;height:36px;border-radius:50%;object-fit:cover;" alt="">
                <div><div class="fw-semibold small"><?= e($u['first_name'].' '.$u['last_name']) ?></div><div class="text-muted" style="font-size:.75rem;"><?= e($u['email']) ?></div></div>
              </div>
            </td>
            <td class="small text-muted"><?= e($u['phone'] ?? '—') ?></td>
            <td><?= $u['plan_name'] ? '<span class="badge bg-success">'.e($u['plan_name']).'</span>' : '<span class="badge bg-secondary">Free</span>' ?></td>
            <td><?= status_badge($u['status']) ?></td>
            <td class="small text-muted"><?= format_date($u['created_at'],'d M Y') ?></td>
            <td class="text-end">
              <a href="<?= url('admin/viewUser/'.$u['id']) ?>" class="btn btn-sm btn-outline-primary" title="View">
                <i class="bi bi-eye"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($rows)): ?><tr><td colspan="6" class="text-center py-4 text-muted">No customers found.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if($pager['total_pages']>1): ?><div class="card-footer bg-white"><?= render_pagination($pager) ?></div><?php endif; ?>
</div>

<?php require_once VIEWS_PATH . DS . 'layouts' . DS . 'admin_footer.php'; ?>
