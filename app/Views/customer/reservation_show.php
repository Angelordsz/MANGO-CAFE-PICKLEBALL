<?php
$r        = $reservation;
$isHall   = $r['reservation_type'] === 'function_hall';
$due      = (float) $r['total_amount'] - (float) $r['amount_paid'];
$isActive = in_array($r['status'], ['pending', 'approved'], true);
?>

<div class="row mb-2" style="gap:10px">
  <a href="<?= url('/reservations') ?>" class="icon-btn" style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div class="grow">
    <h1 style="margin:0"><?= e($r['facility_name']) ?></h1>
    <p class="small muted" style="margin:0"><?= e($r['reservation_code']) ?></p>
  </div>
  <span class="badge badge-<?= status_badge($r['status']) ?>"><?= e(label($r['status'])) ?></span>
</div>

<?php if ($r['status'] === 'pending'): ?>
  <div class="alert alert-warning mb-2">
    <?= icon('clock', 18) ?>
    <span><strong>Awaiting confirmation.</strong> Your time slot is held while the café reviews this request.</span>
  </div>
<?php elseif ($r['status'] === 'approved'): ?>
  <div class="alert alert-success mb-2">
    <?= icon('check', 18) ?>
    <span><strong>Confirmed.</strong> See you at Mango Drive Café. Please arrive 10 minutes early.</span>
  </div>
<?php elseif ($r['status'] === 'rejected'): ?>
  <div class="alert alert-error mb-2">
    <?= icon('x', 18) ?>
    <span>
      <strong>Declined.</strong>
      <?= $r['admin_remarks'] ? e($r['admin_remarks']) : 'Please choose another time or contact the café.' ?>
    </span>
  </div>
<?php elseif ($r['status'] === 'cancelled'): ?>
  <div class="alert alert-error mb-2">
    <?= icon('info', 18) ?>
    <span><strong>Cancelled.</strong> <?= e($r['cancel_reason'] ?? '') ?></span>
  </div>
<?php endif; ?>

<div class="card card-lg mb-2">
  <div class="summary">
    <div class="summary-row">
      <span class="muted"><?= icon('calendar', 14) ?> Date</span>
      <strong><?= e(fdate($r['reservation_date'], 'l, F j, Y')) ?></strong>
    </div>
    <div class="summary-row">
      <span class="muted"><?= icon('clock', 14) ?> Time</span>
      <strong><?= e(ftimerange($r['start_time'], $r['end_time'])) ?></strong>
    </div>
    <div class="summary-row">
      <span class="muted">Duration</span>
      <strong><?= (int) $r['duration_hours'] ?> <?= pluralise((int) $r['duration_hours'], 'hour') ?></strong>
    </div>
    <div class="summary-row">
      <span class="muted"><?= $isHall ? 'Expected guests' : 'Players' ?></span>
      <strong><?= (int) $r['party_size'] ?></strong>
    </div>
    <?php if ($r['purpose']): ?>
      <div class="summary-row">
        <span class="muted">Purpose</span>
        <strong><?= e($r['purpose']) ?></strong>
      </div>
    <?php endif; ?>
    <div class="summary-row">
      <span class="muted"><?= icon('pin', 14) ?> Location</span>
      <strong>Mango Drive Café</strong>
    </div>
  </div>
</div>

<div class="card mb-2">
  <p class="label">Payment</p>
  <div class="summary">
    <?php foreach ($slots as $slot): ?>
      <div class="summary-row">
        <span class="muted"><?= e(ftime($slot['start_time'])) ?> – <?= e(ftime($slot['end_time'])) ?></span>
        <span><?= money($slot['price']) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="summary-row summary-total">
      <span>Total</span>
      <span><?= money($r['total_amount']) ?></span>
    </div>
    <?php if ((float) $r['amount_paid'] > 0): ?>
      <div class="summary-row">
        <span class="muted">Recorded as paid</span>
        <span style="color:var(--ok)"><?= money($r['amount_paid']) ?></span>
      </div>
      <?php if ($due > 0): ?>
        <div class="summary-row">
          <span class="muted">Balance</span>
          <strong><?= money($due) ?></strong>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <p class="field-help mt-1">
    <?= icon('info', 12) ?>
    Payment is collected at the café counter. Staff will record it against this reservation.
  </p>
</div>

<?php if ($r['customer_notes']): ?>
  <div class="card mb-2">
    <p class="label">Your notes</p>
    <p class="small" style="margin:0"><?= e($r['customer_notes']) ?></p>
  </div>
<?php endif; ?>

<?php if ($r['admin_remarks'] && $r['status'] !== 'rejected'): ?>
  <div class="card mb-2">
    <p class="label">Note from the café</p>
    <p class="small" style="margin:0"><?= e($r['admin_remarks']) ?></p>
  </div>
<?php endif; ?>

<?php if ($isActive): ?>
  <?php if ($cancellable): ?>
    <div class="card">
      <p class="label">Need to change this?</p>
      <div class="btn-group">
        <a href="<?= url('/reservations/' . (int) $r['id'] . '/reschedule') ?>" class="btn btn-outline">
          <?= icon('calendar', 15) ?> Reschedule
        </a>
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('cancel-box').hidden = false; this.hidden = true;">
          <?= icon('x', 15) ?> Cancel reservation
        </button>
      </div>

      <form method="post" action="<?= url('/reservations/' . (int) $r['id'] . '/cancel') ?>"
            id="cancel-box" hidden class="mt-2" data-guard>
        <?= csrf_field() ?>
        <div class="field">
          <label for="cancel_reason">Reason for cancelling</label>
          <input type="text" id="cancel_reason" name="cancel_reason" class="input"
                 placeholder="Change of plans" maxlength="255" required>
        </div>
        <button type="submit" class="btn btn-danger btn-block"
                data-confirm="Cancel this reservation? This cannot be undone.">
          Confirm cancellation
        </button>
      </form>
    </div>
  <?php else: ?>
    <div class="alert alert-info">
      <?= icon('clock', 18) ?>
      <span>
        This reservation is within <?= (int) $cutoffHours ?> hours of its start time, so it can no
        longer be changed online. Please call Mango Drive Café.
      </span>
    </div>
  <?php endif; ?>
<?php endif; ?>
