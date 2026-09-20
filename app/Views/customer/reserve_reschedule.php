<?php $r = $reservation; ?>

<div class="row mb-2" style="gap:10px">
  <a href="<?= url('/reservations/' . $r['reservation_code']) ?>" class="icon-btn"
     style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div>
    <h1 style="margin:0">Reschedule</h1>
    <p class="small muted" style="margin:0"><?= e($r['reservation_code']) ?> · <?= e($r['facility_name']) ?></p>
  </div>
</div>

<div class="alert alert-info mb-2">
  <?= icon('info', 18) ?>
  <span>
    Currently booked for <strong><?= e(fdate($r['reservation_date'])) ?></strong>,
    <?= e(ftimerange($r['start_time'], $r['end_time'])) ?>.
    Picking a new time cancels the old slot and submits a fresh request for approval.
  </span>
</div>

<div class="card mb-2">
  <div class="row-between mb-1">
    <p class="label" style="margin:0">New date</p>
    <form method="get" action="<?= url('/reservations/' . (int) $r['id'] . '/reschedule') ?>">
      <input type="date" name="date" class="input" style="padding:6px 9px;font-size:.82rem"
             value="<?= e($date) ?>" min="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
    </form>
  </div>

  <div class="daystrip" data-daystrip>
    <?php foreach ($days as $day): ?>
      <div class="day <?= $day['date'] === $date ? 'active' : '' ?>" data-date="<?= e($day['date']) ?>"
           role="button" tabindex="0">
        <div class="day-dow"><?= e($day['dow']) ?></div>
        <div class="day-num"><?= e($day['day']) ?></div>
        <div class="day-mon"><?= e($day['month']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<form method="post" action="<?= url('/reservations/' . (int) $r['id'] . '/reschedule') ?>" data-slot-form data-guard>
  <?= csrf_field() ?>

  <div class="card mb-2">
    <p class="label">New time</p>

    <?php if (!$slots): ?>
      <div class="empty">
        <span class="empty-icon"><?= icon('calendar', 24) ?></span>
        <h3>No schedule for this date</h3>
        <p class="small">Try another day.</p>
      </div>
    <?php else: ?>
      <div class="slots mb-2">
        <?php $index = 0; foreach ($slots as $slot): ?>
          <?php
            $state    = $slot['slot_state'];
            $tooSoon  = strtotime($date . ' ' . $slot['start_time']) - time() < $minHour * 3600;
            $disabled = $state !== 'available' || $tooSoon;
            $class    = $state === 'booked' ? 'is-taken' : ($disabled ? 'is-blocked' : '');
            $index++;
          ?>
          <label class="slot <?= $class ?>">
            <input type="checkbox" name="slots[]" value="<?= (int) $slot['id'] ?>"
                   data-index="<?= $index ?>"
                   data-price="<?= e($slot['price']) ?>"
                   data-start="<?= e(ftime($slot['start_time'])) ?>"
                   data-end="<?= e(ftime($slot['end_time'])) ?>"
                   <?= $disabled ? 'disabled' : '' ?>>
            <span class="slot-time"><?= e(ftime($slot['start_time'])) ?></span>
            <span class="slot-price">
              <?= $state === 'booked' ? 'Booked' : ($tooSoon ? 'Too soon' : ($state !== 'available' ? label($state) : money($slot['price']))) ?>
            </span>
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
        <div class="summary-row">
          <span class="muted">New time</span>
          <span data-range>No time selected</span>
        </div>
        <div class="summary-row">
          <span class="muted">Duration</span>
          <span data-hours>0 hours</span>
        </div>
        <div class="summary-row summary-total">
          <span>New total</span>
          <span data-total>₱0.00</span>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg" data-submit disabled
              data-confirm="Reschedule this reservation? The original slot will be released."
              data-loading="Rescheduling…">
        Confirm new time
      </button>
    </div>
  <?php endif; ?>
</form>
