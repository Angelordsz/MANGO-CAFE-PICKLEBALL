<div class="row-between mb-2">
  <h1 style="margin:0">My reservations</h1>
  <a href="<?= url('/reserve') ?>" class="btn btn-primary btn-sm"><?= icon('plus', 15) ?> New</a>
</div>

<nav class="tabs" aria-label="Filter reservations">
  <?php
  $tabs = ['upcoming' => 'Upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled', 'all' => 'All'];
  foreach ($tabs as $key => $labelText): ?>
    <a href="<?= url('/reservations?filter=' . $key) ?>" class="<?= $filter === $key ? 'active' : '' ?>">
      <?= e($labelText) ?>
    </a>
  <?php endforeach; ?>
</nav>

<?php if (!$reservations): ?>
  <div class="card">
    <div class="empty">
      <span class="empty-icon"><?= icon('ticket', 26) ?></span>
      <h3>Nothing here yet</h3>
      <p class="small mb-2">
        <?= $filter === 'upcoming'
              ? 'You have no upcoming reservations.'
              : 'No reservations match this filter.' ?>
      </p>
      <a href="<?= url('/reserve') ?>" class="btn btn-primary">Reserve a facility</a>
    </div>
  </div>
<?php else: ?>
  <div class="grid grid-2">
    <?php foreach ($reservations as $reservation): ?>
      <?php
        $isPast = strtotime($reservation['reservation_date'] . ' ' . $reservation['end_time']) < time();
        $due    = (float) $reservation['total_amount'] - (float) $reservation['amount_paid'];
      ?>
      <a href="<?= url('/reservations/' . $reservation['reservation_code']) ?>" class="card card-link">
        <div class="row-between mb-1">
          <span class="badge badge-<?= status_badge($reservation['status']) ?>">
            <?= e(label($reservation['status'])) ?>
          </span>
          <span class="tiny muted"><?= e($reservation['reservation_code']) ?></span>
        </div>

        <div class="row mb-1" style="gap:10px">
          <span class="thumb" style="width:42px;height:42px">
            <?= icon($reservation['reservation_type'] === 'court' ? 'court' : 'hall', 19) ?>
          </span>
          <div class="grow">
            <strong style="display:block"><?= e($reservation['facility_name']) ?></strong>
            <span class="small muted">
              <?= e(human_date($reservation['reservation_date'])) ?>
              · <?= e(ftimerange($reservation['start_time'], $reservation['end_time'])) ?>
            </span>
          </div>
        </div>

        <div class="row-between small">
          <span class="muted">
            <?= (int) $reservation['duration_hours'] ?> <?= pluralise((int) $reservation['duration_hours'], 'hour') ?>
            · <?= (int) $reservation['party_size'] ?> pax
          </span>
          <?php if ($due > 0 && !$isPast && $reservation['status'] !== 'cancelled' && $reservation['status'] !== 'rejected'): ?>
            <strong><?= money($due) ?> due</strong>
          <?php elseif ((float) $reservation['amount_paid'] > 0): ?>
            <span class="badge badge-ok">Paid</span>
          <?php else: ?>
            <strong><?= money($reservation['total_amount']) ?></strong>
          <?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
