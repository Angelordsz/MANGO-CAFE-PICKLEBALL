<div class="row-between mb-2">
  <div>
    <h1 style="margin:0">Function halls</h1>
    <p class="small muted" style="margin:0">Event spaces available for reservation.</p>
  </div>
  <a href="<?= url('/admin/halls/create') ?>" class="btn btn-primary btn-sm no-print">
    <?= icon('plus', 15) ?> Add hall
  </a>
</div>

<?php if (!$halls): ?>
  <div class="card">
    <div class="empty">
      <span class="empty-icon"><?= icon('hall', 26) ?></span>
      <h3>No function halls yet</h3>
      <p class="small mb-2">Add one, then generate its schedule so customers can book it.</p>
      <a href="<?= url('/admin/halls/create') ?>" class="btn btn-primary">Add a function hall</a>
    </div>
  </div>
<?php else: ?>
  <div class="grid grid-2">
    <?php foreach ($halls as $hall): ?>
      <article class="card">
        <div class="row-between mb-1">
          <h3 style="margin:0"><?= e($hall['hall_name']) ?></h3>
          <span class="badge badge-<?= status_badge($hall['status']) ?>"><?= e(label($hall['status'])) ?></span>
        </div>

        <p class="small muted mb-2"><code class="tiny"><?= e($hall['hall_code']) ?></code></p>

        <div class="summary mb-2">
          <div class="summary-row">
            <span class="muted">Rental fee</span><strong><?= money($hall['rental_fee']) ?>/hour</strong>
          </div>
          <div class="summary-row">
            <span class="muted">Capacity</span><strong><?= (int) $hall['capacity'] ?> guests</strong>
          </div>
          <div class="summary-row">
            <span class="muted">Minimum booking</span><strong><?= (int) $hall['min_hours'] ?> hours</strong>
          </div>
          <div class="summary-row">
            <span class="muted">Future slots</span><strong><?= (int) $hall['future_slots'] ?></strong>
          </div>
          <div class="summary-row">
            <span class="muted">Upcoming bookings</span><strong><?= (int) $hall['upcoming_bookings'] ?></strong>
          </div>
        </div>

        <?php if ($hall['amenities']): ?>
          <div class="row row-wrap mb-2" style="gap:5px">
            <?php foreach (array_map('trim', explode(',', $hall['amenities'])) as $amenity): ?>
              <span class="pill tiny"><?= e(ucfirst($amenity)) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="btn-group no-print">
          <a href="<?= url('/admin/halls/' . (int) $hall['id'] . '/edit') ?>" class="btn btn-sm btn-outline grow">
            <?= icon('edit', 14) ?> Edit
          </a>
          <a href="<?= url('/admin/schedules?type=function_hall&facility=' . (int) $hall['id']) ?>" class="btn btn-sm btn-outline">
            <?= icon('calendar', 14) ?> Schedule
          </a>
          <form method="post" action="<?= url('/admin/halls/' . (int) $hall['id'] . '/delete') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-ghost" aria-label="Delete"
                    data-confirm="Delete this hall? If it has booking history it will be deactivated instead.">
              <?= icon('trash', 14) ?>
            </button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
