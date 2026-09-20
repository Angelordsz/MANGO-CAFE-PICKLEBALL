<div class="row-between mb-2">
  <div>
    <h1 style="margin:0">System logs</h1>
    <p class="small muted" style="margin:0">Audit trail of every state change in the system.</p>
  </div>
  <a href="<?= url('/admin/logs?' . http_build_query(array_merge($filters, ['export' => 'csv']))) ?>"
     class="btn btn-outline btn-sm no-print">
    <?= icon('download', 15) ?> Export CSV
  </a>
</div>

<form method="get" action="<?= url('/admin/logs') ?>" class="filters no-print" data-auto-filter>
  <div class="field">
    <label for="q">Search</label>
    <input type="search" id="q" name="q" class="input" value="<?= e($filters['q']) ?>" placeholder="Description">
  </div>
  <div class="field">
    <label for="action">Action</label>
    <select id="action" name="action" class="select">
      <option value="">All actions</option>
      <?php foreach ($actions as $action): ?>
        <option value="<?= e($action['prefix']) ?>" <?= $filters['action'] === $action['prefix'] ? 'selected' : '' ?>>
          <?= e(label($action['prefix'])) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="user_id">User</label>
    <select id="user_id" name="user_id" class="select">
      <option value="">All users</option>
      <?php foreach ($users as $user): ?>
        <option value="<?= (int) $user['id'] ?>" <?= $filters['user_id'] === (string) $user['id'] ? 'selected' : '' ?>>
          <?= e($user['username']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="from">From</label>
    <input type="date" id="from" name="from" class="input" value="<?= e($filters['from']) ?>">
  </div>
  <div class="field">
    <label for="to">To</label>
    <input type="date" id="to" name="to" class="input" value="<?= e($filters['to']) ?>">
  </div>
  <div class="field" style="flex:0 0 auto">
    <button type="submit" class="btn btn-outline"><?= icon('filter', 15) ?> Filter</button>
  </div>
</form>

<div class="card card-flush">
  <?php if (!$logs): ?>
    <div class="empty">
      <span class="empty-icon"><?= icon('list', 26) ?></span>
      <h3>No log entries</h3>
      <p class="small">Try widening the date range.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table table-compact">
        <thead>
          <tr>
            <th>When</th><th>User</th><th>Action</th><th>Description</th><th>Entity</th><th>IP</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td class="small nowrap muted"><?= e(fdate($log['created_at'], 'M j, H:i:s')) ?></td>
              <td class="small">
                <?php if ($log['username']): ?>
                  <strong><?= e($log['username']) ?></strong>
                  <span class="tiny muted" style="display:block"><?= e(label($log['role'])) ?></span>
                <?php else: ?>
                  <span class="muted">system</span>
                <?php endif; ?>
              </td>
              <td><code class="tiny"><?= e($log['action']) ?></code></td>
              <td class="small"><?= e($log['description']) ?></td>
              <td class="tiny muted">
                <?= $log['entity_type'] ? e($log['entity_type']) . ' #' . (int) $log['entity_id'] : '—' ?>
              </td>
              <td class="tiny muted"><?= e($log['ip_address'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<p class="tiny muted mt-2">
  Showing up to 500 entries. Use the filters or export to CSV for a full review.
</p>
