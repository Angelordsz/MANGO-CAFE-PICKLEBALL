<div class="row-between mb-2">
  <div>
    <h1 style="margin:0">Reservations</h1>
    <p class="small muted" style="margin:0">Approve, reject, reschedule and track every booking.</p>
  </div>
  <a href="<?= url('/admin/reservations/create') ?>" class="btn btn-primary btn-sm no-print">
    <?= icon('plus', 15) ?> New booking
  </a>
</div>

<div class="grid grid-4 mb-2">
  <a href="<?= url('/admin/reservations?status=pending') ?>" class="stat stat-accent card-link">
    <p class="stat-label">Pending</p>
    <p class="stat-value"><?= (int) $counts['pending'] ?></p>
  </a>
  <a href="<?= url('/admin/reservations?status=approved') ?>" class="stat card-link">
    <p class="stat-label">Approved</p>
    <p class="stat-value"><?= (int) $counts['approved'] ?></p>
  </a>
  <a href="<?= url('/admin/reservations?status=completed') ?>" class="stat card-link">
    <p class="stat-label">Completed</p>
    <p class="stat-value"><?= (int) $counts['completed'] ?></p>
  </a>
  <a href="<?= url('/admin/reservations?status=cancelled') ?>" class="stat card-link">
    <p class="stat-label">Cancelled</p>
    <p class="stat-value"><?= (int) $counts['cancelled'] ?></p>
  </a>
</div>

<form method="get" action="<?= url('/admin/reservations') ?>" class="filters no-print" data-auto-filter>
  <div class="field">
    <label for="q">Search</label>
    <input type="search" id="q" name="q" class="input" value="<?= e($filters['q']) ?>"
           placeholder="Code, name or phone">
  </div>

  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status" class="select">
      <option value="">All statuses</option>
      <?php foreach (['pending','approved','rejected','cancelled','completed','no_show'] as $status): ?>
        <option value="<?= $status ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
          <?= e(label($status)) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="field">
    <label for="type">Facility</label>
    <select id="type" name="type" class="select">
      <option value="">All facilities</option>
      <option value="court"         <?= $filters['type'] === 'court' ? 'selected' : '' ?>>Courts</option>
      <option value="function_hall" <?= $filters['type'] === 'function_hall' ? 'selected' : '' ?>>Function hall</option>
    </select>
  </div>

  <div class="field">
    <label for="date">Date</label>
    <input type="date" id="date" name="date" class="input" value="<?= e($filters['date']) ?>">
  </div>

  <div class="field" style="flex:0 0 auto">
    <button type="submit" class="btn btn-outline"><?= icon('filter', 15) ?> Filter</button>
  </div>
</form>

<div class="card card-flush">
  <?php if (!$reservations): ?>
    <div class="empty">
      <span class="empty-icon"><?= icon('ticket', 26) ?></span>
      <h3>No reservations found</h3>
      <p class="small">Try clearing the filters.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Code</th>
            <th>Customer</th>
            <th>Facility</th>
            <th>Date &amp; time</th>
            <th>Source</th>
            <th>Status</th>
            <th class="text-right">Amount</th>
            <th class="text-right">Paid</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reservations as $r): ?>
            <tr>
              <td class="nowrap"><code class="tiny"><?= e($r['reservation_code']) ?></code></td>
              <td>
                <strong class="small"><?= e(trim($r['first_name'] . ' ' . $r['last_name'])) ?: '—' ?></strong>
                <span class="tiny muted" style="display:block"><?= e($r['phone_number'] ?? '') ?></span>
              </td>
              <td class="small"><?= e($r['facility_name']) ?></td>
              <td class="nowrap small">
                <?= e(fdate($r['reservation_date'], 'M j')) ?>
                <span class="muted"><?= e(ftimerange($r['start_time'], $r['end_time'])) ?></span>
              </td>
              <td><span class="badge badge-muted"><?= e(label($r['source'])) ?></span></td>
              <td><span class="badge badge-<?= status_badge($r['status']) ?>"><?= e(label($r['status'])) ?></span></td>
              <td class="text-right nowrap"><?= money($r['total_amount']) ?></td>
              <td class="text-right nowrap">
                <?php if ((float) $r['amount_paid'] >= (float) $r['total_amount'] && (float) $r['total_amount'] > 0): ?>
                  <span class="badge badge-ok">Paid</span>
                <?php elseif ((float) $r['amount_paid'] > 0): ?>
                  <span class="badge badge-warn"><?= money($r['amount_paid'], false) ?></span>
                <?php else: ?>
                  <span class="tiny muted">—</span>
                <?php endif; ?>
              </td>
              <td class="text-right">
                <a href="<?= url('/admin/reservations/' . (int) $r['id']) ?>" class="btn btn-sm btn-ghost" aria-label="Open">
                  <?= icon('chevron-right', 15) ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
