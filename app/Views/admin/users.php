<?php use App\Core\Auth; ?>

<div class="row-between mb-2">
  <div>
    <h1 style="margin:0">User accounts</h1>
    <p class="small muted" style="margin:0">Logins for administrators, staff and customers.</p>
  </div>
  <a href="<?= url('/admin/users/create') ?>" class="btn btn-primary btn-sm no-print">
    <?= icon('plus', 15) ?> New account
  </a>
</div>

<div class="grid grid-4 mb-2">
  <div class="stat stat-accent">
    <p class="stat-label">Administrators</p>
    <p class="stat-value"><?= (int) $counts['admins'] ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Staff</p>
    <p class="stat-value"><?= (int) $counts['staff'] ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Customers</p>
    <p class="stat-value"><?= (int) $counts['customers'] ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Suspended</p>
    <p class="stat-value"><?= (int) $counts['suspended'] ?></p>
  </div>
</div>

<div class="alert alert-info mb-2">
  <?= icon('info', 18) ?>
  <span>
    <strong>Staff</strong> can register walk-ins, manage reservations and record payments.
    Only <strong>administrators</strong> can manage users, facilities, settings, system logs,
    and verify payments.
  </span>
</div>

<form method="get" action="<?= url('/admin/users') ?>" class="filters no-print" data-auto-filter>
  <div class="field">
    <label for="q">Search</label>
    <input type="search" id="q" name="q" class="input" value="<?= e($q) ?>" placeholder="Username, email or name">
  </div>
  <div class="field">
    <label for="role">Role</label>
    <select id="role" name="role" class="select">
      <option value="">All roles</option>
      <option value="admin"    <?= $role === 'admin' ? 'selected' : '' ?>>Administrator</option>
      <option value="staff"    <?= $role === 'staff' ? 'selected' : '' ?>>Staff</option>
      <option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Customer</option>
    </select>
  </div>
  <div class="field" style="flex:0 0 auto">
    <button type="submit" class="btn btn-outline"><?= icon('filter', 15) ?> Filter</button>
  </div>
</form>

<div class="card card-flush">
  <?php if (!$users): ?>
    <div class="empty">
      <span class="empty-icon"><?= icon('user', 26) ?></span>
      <h3>No accounts found</h3>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Username</th><th>Name</th><th>Email</th><th>Role</th>
            <th>Status</th><th>Last login</th><th class="no-print">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
            <tr>
              <td>
                <div class="row" style="gap:8px">
                  <span class="avatar avatar-sm"><?= e(strtoupper(substr($user['username'], 0, 2))) ?></span>
                  <strong class="small"><?= e($user['username']) ?></strong>
                </div>
              </td>
              <td class="small">
                <?php if ($user['player_id']): ?>
                  <a href="<?= url('/admin/players/' . (int) $user['player_id']) ?>">
                    <?= e(trim($user['first_name'] . ' ' . $user['last_name'])) ?>
                  </a>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td class="small muted"><?= e($user['email']) ?></td>
              <td>
                <span class="badge badge-<?= $user['role'] === 'admin' ? 'accent' : ($user['role'] === 'staff' ? 'info' : 'muted') ?>">
                  <?= e(label($user['role'])) ?>
                </span>
              </td>
              <td><span class="badge badge-<?= status_badge($user['status']) ?>"><?= e(label($user['status'])) ?></span></td>
              <td class="small muted nowrap">
                <?= $user['last_login_at'] ? e(fdate($user['last_login_at'], 'M j, g:i A')) : 'Never' ?>
              </td>
              <td class="no-print nowrap">
                <a href="<?= url('/admin/users/' . (int) $user['id'] . '/edit') ?>"
                   class="btn btn-sm btn-ghost" title="Edit"><?= icon('edit', 14) ?></a>

                <form method="post" action="<?= url('/admin/users/' . (int) $user['id'] . '/reset') ?>" style="display:inline">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-ghost" title="Reset password"
                          data-confirm="Issue a temporary password for this account?">
                    <?= icon('settings', 14) ?>
                  </button>
                </form>

                <?php if ((int) $user['id'] !== (int) Auth::id()): ?>
                  <form method="post" action="<?= url('/admin/users/' . (int) $user['id'] . '/status') ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-ghost"
                            title="<?= $user['status'] === 'active' ? 'Suspend' : 'Reactivate' ?>"
                            data-confirm="<?= $user['status'] === 'active' ? 'Suspend this account?' : 'Reactivate this account?' ?>">
                      <?= icon($user['status'] === 'active' ? 'x' : 'check', 14) ?>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
