<?php
$isHall     = $type === 'function_hall';
$first      = $slots[0];
$last       = $slots[count($slots) - 1];
$partySize  = (int) ($_GET['party_size'] ?? ($isHall ? 20 : 4));
$purpose    = (string) ($_GET['purpose'] ?? '');
$notes      = (string) ($_GET['customer_notes'] ?? '');
$backQuery  = ($isHall ? '/reserve/hall' : '/reserve/court')
            . '?facility=' . $facility['id'] . '&date=' . $first['slot_date'];
?>

<div class="row mb-2" style="gap:10px">
  <a href="<?= url($backQuery) ?>" class="icon-btn" style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div>
    <h1 style="margin:0">Review your reservation</h1>
    <p class="small muted" style="margin:0">Check the details, then submit your request.</p>
  </div>
</div>

<div class="steps">
  <div class="step done"><div class="step-bar"></div>Facility</div>
  <div class="step done"><div class="step-bar"></div>Date</div>
  <div class="step done"><div class="step-bar"></div>Time</div>
  <div class="step current"><div class="step-bar"></div>Confirm</div>
</div>

<div class="card card-lg mb-2">
  <div class="row mb-2" style="gap:12px">
    <span class="thumb" style="width:52px;height:52px">
      <?= icon($isHall ? 'hall' : 'court', 24) ?>
    </span>
    <div class="grow">
      <h2 style="margin:0"><?= e($facility['name']) ?></h2>
      <p class="small muted" style="margin:0">
        Mango Drive Café
        <?= $facility['court_type'] ? ' · ' . e(label($facility['court_type'])) : '' ?>
      </p>
    </div>
  </div>

  <div class="summary">
    <div class="summary-row">
      <span class="muted"><?= icon('calendar', 14) ?> Date</span>
      <strong><?= e(fdate($first['slot_date'], 'l, F j, Y')) ?></strong>
    </div>
    <div class="summary-row">
      <span class="muted"><?= icon('clock', 14) ?> Time</span>
      <strong><?= e(ftime($first['start_time'])) ?> – <?= e(ftime($last['end_time'])) ?></strong>
    </div>
    <div class="summary-row">
      <span class="muted">Duration</span>
      <strong><?= count($slots) ?> <?= pluralise(count($slots), 'hour') ?></strong>
    </div>
    <div class="summary-row">
      <span class="muted"><?= $isHall ? 'Expected guests' : 'Players' ?></span>
      <strong><?= $partySize ?></strong>
    </div>
    <?php if ($purpose !== ''): ?>
      <div class="summary-row">
        <span class="muted">Purpose</span>
        <strong><?= e($purpose) ?></strong>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card mb-2">
  <p class="label">Price breakdown</p>
  <div class="summary">
    <?php foreach ($slots as $slot): ?>
      <div class="summary-row">
        <span class="muted"><?= e(ftime($slot['start_time'])) ?> – <?= e(ftime($slot['end_time'])) ?></span>
        <span><?= money($slot['price']) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="summary-row summary-total">
      <span>Total due at the counter</span>
      <span><?= money($total) ?></span>
    </div>
  </div>
</div>

<div class="alert alert-info mb-2">
  <?= icon('info', 18) ?>
  <span>
    <strong>This is a request, not a confirmed booking.</strong>
    Mango Drive Café will review it and you will get a notification once it is approved.
    Payment is settled at the counter — nothing is charged online.
  </span>
</div>

<form method="post" action="<?= url('/reserve') ?>" data-guard>
  <?= csrf_field() ?>
  <input type="hidden" name="reservation_type" value="<?= e($type) ?>">
  <input type="hidden" name="facility_id" value="<?= (int) $facility['id'] ?>">
  <input type="hidden" name="party_size" value="<?= $partySize ?>">
  <input type="hidden" name="purpose" value="<?= e($purpose) ?>">
  <input type="hidden" name="customer_notes" value="<?= e($notes) ?>">
  <?php foreach ($slots as $slot): ?>
    <input type="hidden" name="slots[]" value="<?= (int) $slot['id'] ?>">
  <?php endforeach; ?>

  <div class="btn-group">
    <button type="submit" class="btn btn-primary btn-lg grow" data-loading="Submitting…">
      Submit reservation request
    </button>
    <a href="<?= url($backQuery) ?>" class="btn btn-outline btn-lg">Change time</a>
  </div>
</form>
