<?php $r = $reservation; ?>

<div class="row mb-2" style="gap:10px">
  <a href="<?= url('/admin/reservations/' . (int) $r['id']) ?>" class="icon-btn"
     style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div>
    <h1 style="margin:0">Reschedule <?= e($r['reservation_code']) ?></h1>
    <p class="small muted" style="margin:0"><?= e($r['facility_name']) ?></p>
  </div>
</div>

<div class="alert alert-info mb-2">
  <?= icon('info', 18) ?>
  <span>
    Currently <strong><?= e(fdate($r['reservation_date'])) ?></strong>,
    <?= e(ftimerange($r['start_time'], $r['end_time'])) ?>.
    The new booking is created as <strong>approved</strong> and the old slot is released.
  </span>
</div>

<div class="card mb-2">
  <div class="row-between">
    <p class="label" style="margin:0">New date</p>
    <input type="date" class="input" style="padding:6px 9px;font-size:.82rem;max-width:170px"
           value="<?= e($date) ?>" min="<?= date('Y-m-d') ?>"
           onchange="location.href='<?= url('/admin/reservations/' . (int) $r['id'] . '/reschedule?date=') ?>' + this.value">
  </div>
</div>

<form method="post" action="<?= url('/admin/reservations/' . (int) $r['id'] . '/reschedule') ?>" data-slot-form data-guard>
  <?= csrf_field() ?>

  <div class="card mb-2">
    <h2 class="mb-2">New time — <?= e(fdate($date, 'D, M j')) ?></h2>

    <?php if (!$slots): ?>
      <div class="empty">
        <span class="empty-icon"><?= icon('calendar', 24) ?></span>
        <h3>No slots for this date</h3>
      </div>
    <?php else: ?>
      <div class="slots mb-2">
        <?php $index = 0; foreach ($slots as $slot): ?>
          <?php
            $state    = $slot['slot_state'];
            $disabled = $state !== 'available';
            $class    = $state === 'booked' ? 'is-taken' : ($disabled ? 'is-blocked' : '');
            $index++;
          ?>
          <label class="slot <?= $class ?>">
            <input type="checkbox" name="slots[]" value="<?= (int) $slot['id'] ?>"
                   data-index="<?= $index ?>" data-price="<?= e($slot['price']) ?>"
                   data-start="<?= e(ftime($slot['start_time'])) ?>" data-end="<?= e(ftime($slot['end_time'])) ?>"
                   <?= $disabled ? 'disabled' : '' ?>>
            <span class="slot-time"><?= e(ftime($slot['start_time'])) ?></span>
            <span class="slot-price"><?= $state === 'available' ? money($slot['price']) : label($state) ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="legend">
        <span><i class="l-free"></i> Available</span>
        <span><i class="l-sel"></i> Selected</span>
        <span><i class="l-taken"></i> Unavailable</span>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($slots): ?>
    <div class="card card-lg">
      <div class="summary mb-2">
        <div class="summary-row"><span class="muted">New time</span><span data-range>No time selected</span></div>
        <div class="summary-row"><span class="muted">Duration</span><span data-hours>0 hours</span></div>
        <div class="summary-row summary-total"><span>New total</span><span data-total>₱0.00</span></div>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg" data-submit disabled
              data-confirm="Reschedule this booking? The original slot will be released."
              data-loading="Rescheduling…">
        Confirm new time
      </button>
    </div>
  <?php endif; ?>
</form>
