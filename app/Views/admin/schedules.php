<?php
$isHall   = $type === 'function_hall';
$dow      = (int) date('w', strtotime($date));
$dayHours = $hours[$dow] ?? null;
$coverage = array_column($coverage, 'open_slots', 'slot_date');
?>

<div class="row-between mb-2">
  <div>
    <h1 style="margin:0">Facility schedules</h1>
    <p class="small muted" style="margin:0">Generate bookable time slots and block them for maintenance.</p>
  </div>
</div>

<nav class="tabs no-print" aria-label="Facility type">
  <a href="<?= url('/admin/schedules?type=court') ?>" class="<?= !$isHall ? 'active' : '' ?>">Courts</a>
  <a href="<?= url('/admin/schedules?type=function_hall') ?>" class="<?= $isHall ? 'active' : '' ?>">Function hall</a>
</nav>

<!-- Generate slots -->
<div class="card mb-3">
  <h2 class="mb-1">Generate time slots</h2>
  <p class="small muted mb-2">
    Creates one hourly slot per open hour, using the café's operating hours.
    Safe to re-run — existing slots are left untouched.
  </p>

  <form method="post" action="<?= url('/admin/schedules/generate') ?>" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="facility_type" value="<?= e($type) ?>">

    <div class="row row-wrap" style="gap:9px;align-items:flex-end">
      <div class="field" style="margin:0;flex:1 1 170px">
        <label for="facility_id"><?= $isHall ? 'Hall' : 'Court' ?></label>
        <select id="facility_id" name="facility_id" class="select">
          <?php foreach ($facilities as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $f['id'] === $facilityId ? 'selected' : '' ?>>
              <?= e($f['name']) ?> — <?= money($f['rate'], false) ?>/hr
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field" style="margin:0;flex:1 1 140px">
        <label for="from_date">From</label>
        <input type="date" id="from_date" name="from_date" class="input"
               value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
      </div>

      <div class="field" style="margin:0;flex:1 1 140px">
        <label for="to_date">To</label>
        <input type="date" id="to_date" name="to_date" class="input"
               value="<?= date('Y-m-d', strtotime('+29 days')) ?>" min="<?= date('Y-m-d') ?>" required>
      </div>

      <button type="submit" class="btn btn-primary" data-loading="Generating…">
        <?= icon('calendar', 15) ?> Generate
      </button>
    </div>
  </form>

  <?php if ($dayHours): ?>
    <p class="field-help mt-1">
      <?= icon('clock', 12) ?>
      <?= date('l', strtotime($date)) ?>:
      <?= $dayHours['is_closed']
            ? 'closed'
            : e(ftime($dayHours['open_time'])) . ' – ' . e(ftime($dayHours['close_time'])) ?>
      · change these under <a href="<?= url('/admin/settings') ?>">Settings</a>.
    </p>
  <?php endif; ?>
</div>

<!-- Coverage strip -->
<div class="card mb-3">
  <div class="row-between mb-1">
    <p class="label" style="margin:0">Pick a date to manage</p>
    <form method="get" action="<?= url('/admin/schedules') ?>" class="row" style="gap:6px">
      <input type="hidden" name="type" value="<?= e($type) ?>">
      <input type="hidden" name="facility" value="<?= $facilityId ?>">
      <input type="date" name="date" class="input" style="padding:6px 9px;font-size:.82rem"
             value="<?= e($date) ?>" onchange="this.form.submit()">
    </form>
  </div>

  <div class="daystrip">
    <?php foreach ($days as $day): ?>
      <a href="<?= url('/admin/schedules?type=' . $type . '&facility=' . $facilityId . '&date=' . $day['date']) ?>"
         class="day <?= $day['date'] === $date ? 'active' : '' ?>" style="text-decoration:none">
        <div class="day-dow"><?= e($day['dow']) ?></div>
        <div class="day-num"><?= e($day['day']) ?></div>
        <div class="day-mon">
          <?= isset($coverage[$day['date']]) ? (int) $coverage[$day['date']] . ' free' : '—' ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="row row-wrap mt-2" style="gap:6px">
    <?php foreach ($facilities as $f): ?>
      <a href="<?= url('/admin/schedules?type=' . $type . '&facility=' . $f['id'] . '&date=' . e($date)) ?>"
         class="pill" style="<?= $f['id'] === $facilityId ? 'background:var(--accent);color:var(--accent-ink);font-weight:700' : '' ?>">
        <?= e($f['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Slot grid -->
<div class="card mb-3">
  <div class="row-between mb-2">
    <h2 style="margin:0"><?= e(fdate($date, 'l, F j, Y')) ?></h2>
    <span class="badge badge-muted"><?= count($slots) ?> <?= pluralise(count($slots), 'slot') ?></span>
  </div>

  <?php if (!$slots): ?>
    <div class="empty">
      <span class="empty-icon"><?= icon('calendar', 24) ?></span>
      <h3>No slots for this date</h3>
      <p class="small">Use the generator above to create them.</p>
    </div>
  <?php else: ?>
    <div class="slots mb-2">
      <?php foreach ($slots as $slot): ?>
        <?php
          $state = $slot['slot_state'];
          $class = $state === 'booked' ? 'is-taken' : ($state !== 'available' ? 'is-blocked' : '');
        ?>
        <div class="slot <?= $class ?>" style="cursor:default">
          <span class="slot-time"><?= e(ftime($slot['start_time'])) ?></span>
          <span class="slot-price">
            <?php if ($state === 'booked'): ?>
              <a href="<?= url('/admin/reservations/' . (int) $slot['reservation_id']) ?>" class="tiny strong">
                Booked
              </a>
            <?php elseif ($state === 'available'): ?>
              <?= money($slot['price']) ?>
            <?php else: ?>
              <?= e(label($state)) ?>
            <?php endif; ?>
          </span>

          <div class="no-print" style="margin-top:6px">
            <?php if ($state === 'available'): ?>
              <form method="post" action="<?= url('/admin/schedules/block') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="schedule_id" value="<?= (int) $slot['id'] ?>">
                <input type="hidden" name="facility_type" value="<?= e($type) ?>">
                <input type="hidden" name="facility_id" value="<?= $facilityId ?>">
                <input type="hidden" name="slot_date" value="<?= e($date) ?>">
                <input type="hidden" name="block_reason" value="Blocked by staff">
                <button type="submit" class="btn btn-sm btn-ghost tiny" style="padding:3px 8px"
                        data-confirm="Block this slot so it cannot be booked?">Block</button>
              </form>
            <?php elseif ($state === 'blocked' || $state === 'maintenance'): ?>
              <form method="post" action="<?= url('/admin/schedules/' . (int) $slot['id'] . '/unblock') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-ghost tiny" style="padding:3px 8px">Unblock</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="legend">
      <span><i class="l-free"></i> Available</span>
      <span><i class="l-taken"></i> Booked / blocked</span>
    </div>
  <?php endif; ?>
</div>

<!-- Block a range -->
<div class="card no-print">
  <h2 class="mb-1">Block a time range</h2>
  <p class="small muted mb-2">
    For maintenance or a private event. Slots that are already reserved are skipped —
    cancel those reservations first so the customer is told.
  </p>

  <form method="post" action="<?= url('/admin/schedules/block') ?>" data-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="facility_type" value="<?= e($type) ?>">
    <input type="hidden" name="facility_id" value="<?= $facilityId ?>">

    <div class="row row-wrap" style="gap:9px;align-items:flex-end">
      <div class="field" style="margin:0;flex:1 1 140px">
        <label for="block_date">Date</label>
        <input type="date" id="block_date" name="slot_date" class="input" value="<?= e($date) ?>" required>
      </div>
      <div class="field" style="margin:0;flex:1 1 110px">
        <label for="start_time">From</label>
        <input type="time" id="start_time" name="start_time" class="input" value="08:00" step="3600" required>
      </div>
      <div class="field" style="margin:0;flex:1 1 110px">
        <label for="end_time">To</label>
        <input type="time" id="end_time" name="end_time" class="input" value="12:00" step="3600" required>
      </div>
      <div class="field" style="margin:0;flex:2 1 180px">
        <label for="block_reason">Reason</label>
        <input type="text" id="block_reason" name="block_reason" class="input" maxlength="255"
               placeholder="Court resurfacing" required>
      </div>
      <button type="submit" class="btn btn-danger" data-confirm="Block this range?">Block range</button>
    </div>
  </form>
</div>
