<?php
$isHall   = $type === 'function_hall';
$prefill  = (int) ($_GET['player_id'] ?? 0);
?>

<div class="row mb-2" style="gap:10px">
  <a href="<?= url('/admin/reservations') ?>" class="icon-btn" style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div>
    <h1 style="margin:0">New walk-in booking</h1>
    <p class="small muted" style="margin:0">
      Counter bookings are confirmed immediately — no approval step.
    </p>
  </div>
</div>

<nav class="tabs no-print" aria-label="Facility type">
  <a href="<?= url('/admin/reservations/create?type=court&date=' . e($date)) ?>" class="<?= !$isHall ? 'active' : '' ?>">
    Pickleball court
  </a>
  <a href="<?= url('/admin/reservations/create?type=function_hall&date=' . e($date)) ?>" class="<?= $isHall ? 'active' : '' ?>">
    Function hall
  </a>
</nav>

<form method="post" action="<?= url('/admin/reservations') ?>" data-slot-form data-guard>
  <?= csrf_field() ?>
  <input type="hidden" name="reservation_type" value="<?= e($type) ?>">
  <input type="hidden" name="facility_id" value="<?= $facilityId ?>">

  <!-- Customer -->
  <div class="card mb-2">
    <h2 class="mb-1">Customer</h2>
    <p class="small muted mb-2">Search an existing player, or register a walk-in first.</p>

    <div data-player-search="<?= url('/admin/players/search') ?>" style="position:relative">
      <div class="field">
        <label for="player_search">Find player</label>
        <input type="search" id="player_search" class="input" placeholder="Name, phone, email or player code"
               autocomplete="off" <?= $prefill ? 'value=""' : '' ?>>
        <input type="hidden" name="player_id" value="<?= $prefill ?: '' ?>">
      </div>
      <div data-results hidden
           style="border:1px solid var(--border);border-radius:var(--radius-sm);max-height:240px;overflow:auto;margin-top:-8px"></div>
    </div>

    <?php if ($prefill): ?>
      <div class="alert alert-success" style="padding:9px 12px">
        <?= icon('check', 16) ?>
        <span class="small">Player #<?= $prefill ?> selected. Search above to change.</span>
      </div>
    <?php endif; ?>

    <p class="field-help">
      New customer? <a href="<?= url('/admin/players/walk-in') ?>" style="font-weight:700">Register a walk-in player</a>.
    </p>
  </div>

  <!-- Facility + date -->
  <div class="card mb-2">
    <div class="row-between mb-2">
      <h2 style="margin:0">Facility &amp; date</h2>
      <?php // Plain input, not a nested <form> -- nesting forms is invalid HTML. ?>
      <input type="date" class="input" style="padding:6px 9px;font-size:.82rem;max-width:170px"
             value="<?= e($date) ?>"
             onchange="location.href='<?= url('/admin/reservations/create?type=' . $type . '&facility=' . $facilityId . '&date=') ?>' + this.value">
    </div>

    <div class="row row-wrap" style="gap:6px">
      <?php foreach ($facilities as $f): ?>
        <a href="<?= url('/admin/reservations/create?type=' . $type . '&facility=' . $f['id'] . '&date=' . e($date)) ?>"
           class="pill" style="<?= $f['id'] === $facilityId ? 'background:var(--accent);color:var(--accent-ink);font-weight:700' : '' ?>">
          <?= e($f['name']) ?> · <?= money($f['rate'], false) ?>/hr
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Slots -->
  <div class="card mb-2">
    <h2 class="mb-2">Time slots — <?= e(fdate($date, 'D, M j')) ?></h2>

    <?php if (!$slots): ?>
      <div class="empty">
        <span class="empty-icon"><?= icon('calendar', 24) ?></span>
        <h3>No slots generated for this date</h3>
        <p class="small">
          <a href="<?= url('/admin/schedules?type=' . $type . '&facility=' . $facilityId) ?>">Generate the schedule</a> first.
        </p>
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
            <span class="slot-price">
              <?= $state === 'available' ? money($slot['price']) : label($state) ?>
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
    <!-- Details + payment -->
    <div class="card mb-2">
      <h2 class="mb-2">Details</h2>

      <div class="grid grid-3" style="gap:0 12px">
        <div class="field">
          <label for="party_size"><?= $isHall ? 'Expected guests' : 'Players' ?></label>
          <input type="number" id="party_size" name="party_size" class="input" min="1"
                 value="<?= $isHall ? 20 : 4 ?>">
        </div>

        <div class="field">
          <label for="source">Booking source</label>
          <select id="source" name="source" class="select">
            <option value="walk_in">Walk-in</option>
            <option value="phone">Phone</option>
            <option value="online">Online</option>
          </select>
        </div>

        <?php if ($isHall): ?>
          <div class="field">
            <label for="purpose">Purpose</label>
            <input type="text" id="purpose" name="purpose" class="input" maxlength="255"
                   placeholder="Birthday, meeting…">
          </div>
        <?php endif; ?>
      </div>

      <div class="field" style="margin-bottom:0">
        <label for="customer_notes">Notes</label>
        <textarea id="customer_notes" name="customer_notes" class="textarea" rows="2" maxlength="500"></textarea>
      </div>
    </div>

    <div class="card mb-2">
      <h2 class="mb-1">Payment <span class="label-hint">(optional)</span></h2>
      <p class="small muted mb-2">
        Figure 3.5: record the payment now, or leave blank and record it later from the
        reservation page.
      </p>

      <div class="row row-wrap" style="gap:9px;align-items:flex-end">
        <div class="field" style="margin:0;flex:1 1 130px">
          <label for="amount_paid">Amount received</label>
          <input type="number" id="amount_paid" name="amount_paid" class="input" step="0.01" min="0" value="0">
        </div>
        <div class="field" style="margin:0;flex:1 1 130px">
          <label for="payment_method">Method</label>
          <select id="payment_method" name="payment_method" class="select">
            <option value="cash">Cash</option>
            <option value="gcash">GCash</option>
            <option value="maya">Maya</option>
            <option value="bank_transfer">Bank transfer</option>
            <option value="card">Card</option>
          </select>
        </div>
        <div class="field" style="margin:0;flex:1 1 150px">
          <label for="reference_no">Reference no.</label>
          <input type="text" id="reference_no" name="reference_no" class="input" maxlength="60">
        </div>
      </div>
    </div>

    <div class="card card-lg">
      <div class="summary mb-2">
        <div class="summary-row"><span class="muted">Time</span><span data-range>No time selected</span></div>
        <div class="summary-row"><span class="muted">Duration</span><span data-hours>0 hours</span></div>
        <div class="summary-row summary-total"><span>Total</span><span data-total>₱0.00</span></div>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg" data-submit disabled data-loading="Booking…">
        Create booking
      </button>
    </div>
  <?php endif; ?>
</form>
