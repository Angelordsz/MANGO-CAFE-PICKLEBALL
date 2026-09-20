<?php
$isHall   = $type === 'function_hall';
$backUrl  = url('/reserve');
$freeCount = 0;
foreach ($slots as $s) {
    if ($s['slot_state'] === 'available' && !$s['too_soon']) { $freeCount++; }
}
?>

<div class="row mb-2" style="gap:10px">
  <a href="<?= $backUrl ?>" class="icon-btn" style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div>
    <h1 style="margin:0"><?= $isHall ? 'Reserve the function hall' : 'Reserve a court' ?></h1>
    <p class="small muted" style="margin:0">Pick a facility, a date, then your time slots.</p>
  </div>
</div>

<div class="steps">
  <div class="step done"><div class="step-bar"></div>Facility</div>
  <div class="step done"><div class="step-bar"></div>Date</div>
  <div class="step current"><div class="step-bar"></div>Time</div>
  <div class="step"><div class="step-bar"></div>Confirm</div>
</div>

<!-- Step 1: facility -->
<?php if (count($facilities) > 1): ?>
  <div class="card mb-2">
    <p class="label">Choose <?= $isHall ? 'a hall' : 'a court' ?></p>
    <div class="row row-wrap" style="gap:8px">
      <?php foreach ($facilities as $f): ?>
        <a href="<?= url(($isHall ? '/reserve/hall' : '/reserve/court') . '?facility=' . $f['id'] . '&date=' . e($date)) ?>"
           class="pill <?= $f['id'] === $facility['id'] ? 'badge-accent' : '' ?>"
           style="<?= $f['id'] === $facility['id'] ? 'background:var(--accent);color:var(--accent-ink);font-weight:700' : '' ?>">
          <?= icon($isHall ? 'hall' : 'court', 14) ?>
          <?= e($f['name']) ?>
          <span class="tiny">· <?= money($f['rate'], false) ?>/hr</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Step 2: date -->
<div class="card mb-2">
  <div class="row-between mb-1">
    <p class="label" style="margin:0">Choose a date</p>
    <form method="get" action="<?= url($isHall ? '/reserve/hall' : '/reserve/court') ?>" class="row" style="gap:6px">
      <input type="hidden" name="facility" value="<?= (int) $facility['id'] ?>">
      <input type="date" name="date" class="input" style="padding:6px 9px;font-size:.82rem"
             value="<?= e($date) ?>" min="<?= e($minDate) ?>" max="<?= e($maxDate) ?>"
             onchange="this.form.submit()">
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

<!-- Step 3: slots -->
<form method="get" action="<?= url('/reserve/review') ?>" data-slot-form>
  <input type="hidden" name="type" value="<?= e($type) ?>">
  <input type="hidden" name="facility_id" value="<?= (int) $facility['id'] ?>">

  <div class="card mb-2">
    <div class="row-between mb-1">
      <div>
        <p class="label" style="margin:0">Choose your time</p>
        <p class="small muted" style="margin:0">
          <?= e($facility['name']) ?> · <?= e(human_date($date)) ?>
          <?php if ($isHall && $facility['min_hours'] > 1): ?>
            · minimum <?= (int) $facility['min_hours'] ?> hours
          <?php endif; ?>
        </p>
      </div>
      <span class="pill"><?= $freeCount ?> open</span>
    </div>

    <?php if (!$slots): ?>
      <div class="empty">
        <span class="empty-icon"><?= icon('calendar', 24) ?></span>
        <h3>No schedule for this date</h3>
        <p class="small">The café has not opened this date for booking yet. Try another day.</p>
      </div>
    <?php else: ?>
      <div class="slots mb-2">
        <?php $index = 0; foreach ($slots as $slot): ?>
          <?php
            $state    = $slot['slot_state'];
            $tooSoon  = (bool) $slot['too_soon'];
            $disabled = $state !== 'available' || $tooSoon;
            $class    = $state === 'booked' ? 'is-taken' : ($state !== 'available' ? 'is-blocked' : ($tooSoon ? 'is-blocked' : ''));
            $index++;
          ?>
          <label class="slot <?= $class ?>"
                 title="<?= $tooSoon ? 'Too close to start time' : ($state !== 'available' ? e(label($state)) : '') ?>">
            <input type="checkbox" name="slots[]" value="<?= (int) $slot['id'] ?>"
                   data-index="<?= $index ?>"
                   data-price="<?= e($slot['price']) ?>"
                   data-start="<?= e(ftime($slot['start_time'])) ?>"
                   data-end="<?= e(ftime($slot['end_time'])) ?>"
                   <?= $disabled ? 'disabled' : '' ?>>
            <span class="slot-time"><?= e(ftime($slot['start_time'])) ?></span>
            <span class="slot-price">
              <?php if ($state === 'booked'): ?>
                Booked
              <?php elseif ($state !== 'available'): ?>
                <?= e(label($state)) ?>
              <?php elseif ($tooSoon): ?>
                Too soon
              <?php else: ?>
                <?= money($slot['price']) ?>
              <?php endif; ?>
            </span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="legend">
        <span><i class="l-free"></i> Available</span>
        <span><i class="l-sel"></i> Selected</span>
        <span><i class="l-taken"></i> Booked / unavailable</span>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($slots): ?>
    <div class="card mb-2">
      <p class="label">Booking details</p>

      <div class="grid grid-2" style="gap:0 12px">
        <div class="field">
          <label for="party_size"><?= $isHall ? 'Expected guests' : 'Number of players' ?></label>
          <input type="number" id="party_size" name="party_size" class="input"
                 value="<?= $isHall ? 20 : 4 ?>" min="1" max="<?= $isHall ? (int) $facility['capacity'] : 8 ?>">
        </div>

        <?php if ($isHall): ?>
          <div class="field">
            <label for="purpose">Purpose of booking</label>
            <input type="text" id="purpose" name="purpose" class="input"
                   placeholder="Birthday party, meeting…" maxlength="255">
          </div>
        <?php endif; ?>
      </div>

      <div class="field" style="margin-bottom:0">
        <label for="customer_notes">Notes for the café <span class="label-hint">(optional)</span></label>
        <textarea id="customer_notes" name="customer_notes" class="textarea" rows="2"
                  maxlength="500" placeholder="Anything the staff should know?"></textarea>
      </div>
    </div>

    <!-- Running summary -->
    <div class="card card-lg" style="position:sticky;bottom:calc(var(--tab-h) + 10px);z-index:20">
      <div class="summary mb-2">
        <div class="summary-row">
          <span class="muted"><?= e($facility['name']) ?></span>
          <span><?= money($facility['rate']) ?>/hr</span>
        </div>
        <div class="summary-row">
          <span class="muted">Date</span>
          <span><?= e(fdate($date, 'D, M j, Y')) ?></span>
        </div>
        <div class="summary-row">
          <span class="muted">Time</span>
          <span data-range>No time selected</span>
        </div>
        <div class="summary-row">
          <span class="muted">Duration</span>
          <span data-hours>0 hours</span>
        </div>
        <div class="summary-row summary-total">
          <span>Total</span>
          <span data-total>₱0.00</span>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg" data-submit disabled>
        Review reservation
      </button>

      <p class="tiny muted text-center mt-1" style="margin-bottom:0">
        <?= icon('info', 12) ?>
        Payment is made at the café counter — nothing is charged online.
      </p>
    </div>
  <?php endif; ?>
</form>
