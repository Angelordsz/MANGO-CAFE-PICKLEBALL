<div class="row-between mb-2">
  <div>
    <h1 style="margin:0">Courts</h1>
    <p class="small muted" style="margin:0">Pickleball courts available for reservation.</p>
  </div>
  <a href="<?= url('/admin/courts/create') ?>" class="btn btn-primary btn-sm no-print">
    <?= icon('plus', 15) ?> Add court
  </a>
</div>

<?php if (!$courts): ?>
  <div class="card">
    <div class="empty">
      <span class="empty-icon"><?= icon('court', 26) ?></span>
      <h3>No courts yet</h3>
      <p class="small mb-2">Add a court, then generate its schedule so customers can book it.</p>
      <a href="<?= url('/admin/courts/create') ?>" class="btn btn-primary">Add your first court</a>
    </div>
  </div>
<?php else: ?>
  <div class="grid grid-3">
    <?php foreach ($courts as $court): ?>
      <article class="card">
        <div class="row-between mb-1">
          <h3 style="margin:0"><?= e($court['court_name']) ?></h3>
          <span class="badge badge-<?= status_badge($court['status']) ?>"><?= e(label($court['status'])) ?></span>
        </div>

        <p class="small muted mb-2">
          <code class="tiny"><?= e($court['court_code']) ?></code>
          · <?= e(label($court['type'])) ?>
          <?= $court['surface'] ? ' · ' . e(ucfirst($court['surface'])) : '' ?>
        </p>

        <div class="summary mb-2">
          <div class="summary-row">
            <span class="muted">Hourly rate</span><strong><?= money($court['hourly_rate']) ?></strong>
          </div>
          <div class="summary-row">
            <span class="muted">Capacity</span><strong><?= (int) $court['capacity'] ?> players</strong>
          </div>
          <div class="summary-row">
            <span class="muted">Future slots</span><strong><?= (int) $court['future_slots'] ?></strong>
          </div>
          <div class="summary-row">
            <span class="muted">Upcoming bookings</span><strong><?= (int) $court['upcoming_bookings'] ?></strong>
          </div>
        </div>

        <?php if ((int) $court['future_slots'] === 0): ?>
          <div class="alert alert-warning mb-2" style="padding:8px 11px">
            <?= icon('warning', 15) ?>
            <span class="tiny">No schedule generated — this court cannot be booked yet.</span>
          </div>
        <?php endif; ?>

        <div class="btn-group no-print">
          <a href="<?= url('/admin/courts/' . (int) $court['id'] . '/edit') ?>" class="btn btn-sm btn-outline grow">
            <?= icon('edit', 14) ?> Edit
          </a>
          <a href="<?= url('/admin/schedules?type=court&facility=' . (int) $court['id']) ?>" class="btn btn-sm btn-outline">
            <?= icon('calendar', 14) ?> Schedule
          </a>
          <form method="post" action="<?= url('/admin/courts/' . (int) $court['id'] . '/delete') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-ghost" aria-label="Delete"
                    data-confirm="Delete this court? If it has booking history it will be deactivated instead.">
              <?= icon('trash', 14) ?>
            </button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
