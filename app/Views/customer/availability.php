<?php
use App\Core\Auth;

$isHall = $type === 'function_hall';
$dow    = (int) date('w', strtotime($date));
$today  = $hours[$dow] ?? null;
?>

<div class="row-between mb-2">
  <div>
    <h1 style="margin:0">Facility availability</h1>
    <p class="small muted" style="margin:0">
      Mango Drive Café
      <?php if ($today && !$today['is_closed']): ?>
        · open <?= e(ftime($today['open_time'])) ?> – <?= e(ftime($today['close_time'])) ?>
      <?php elseif ($today): ?>
        · <span style="color:var(--bad)">closed on <?= date('l', strtotime($date)) ?></span>
      <?php endif; ?>
    </p>
  </div>
  <?php if (Auth::check() && !Auth::isStaff()): ?>
    <a href="<?= url('/reserve') ?>" class="btn btn-primary btn-sm"><?= icon('plus', 15) ?> Reserve</a>
  <?php endif; ?>
</div>

<nav class="tabs" aria-label="Facility type">
  <a href="<?= url('/availability?type=court&date=' . e($date)) ?>" class="<?= !$isHall ? 'active' : '' ?>">
    Pickleball courts
  </a>
  <a href="<?= url('/availability?type=function_hall&date=' . e($date)) ?>" class="<?= $isHall ? 'active' : '' ?>">
    Function hall
  </a>
</nav>

<div class="card mb-2">
  <div class="row-between mb-1">
    <p class="label" style="margin:0">Date</p>
    <form method="get" action="<?= url('/availability') ?>" class="row" style="gap:6px">
      <input type="hidden" name="type" value="<?= e($type) ?>">
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

<?php if (!$times): ?>
  <div class="card">
    <div class="empty">
      <span class="empty-icon"><?= icon('calendar', 26) ?></span>
      <h3>No schedule published</h3>
      <p class="small">
        The café has not opened <?= e(fdate($date)) ?> for booking yet. Try another date.
      </p>
    </div>
  </div>
<?php else: ?>

  <!-- Wide screens: one row per facility, one column per hour -->
  <div class="card card-flush mb-2">
    <div class="table-wrap">
      <table class="table table-compact">
        <thead>
          <tr>
            <th style="position:sticky;left:0;background:var(--bg-elevated);z-index:1">
              <?= $isHall ? 'Hall' : 'Court' ?>
            </th>
            <?php foreach ($times as $key => $time): ?>
              <th class="text-center"><?= e(date('g A', strtotime($time))) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($grid as $entry): ?>
            <?php
              $facility = $entry['facility'];
              $byTime   = [];
              foreach ($entry['slots'] as $slot) {
                  $byTime[substr($slot['start_time'], 0, 5)] = $slot;
              }
            ?>
            <tr>
              <td style="position:sticky;left:0;background:var(--bg-elevated);z-index:1">
                <strong><?= e($facility['name']) ?></strong>
                <span class="tiny muted" style="display:block"><?= money($facility['rate'], false) ?>/hr</span>
              </td>

              <?php foreach ($times as $key => $time): ?>
                <?php $slot = $byTime[$key] ?? null; ?>
                <td class="text-center">
                  <?php if (!$slot): ?>
                    <span class="tiny muted">—</span>
                  <?php elseif ($slot['slot_state'] === 'available'): ?>
                    <?php if (Auth::check() && !Auth::isStaff()): ?>
                      <a href="<?= url(($isHall ? '/reserve/hall' : '/reserve/court') . '?facility=' . $facility['id'] . '&date=' . e($date)) ?>"
                         class="badge badge-ok" title="Available — click to book"><?= icon('check', 12) ?></a>
                    <?php else: ?>
                      <span class="badge badge-ok" title="Available"><?= icon('check', 12) ?></span>
                    <?php endif; ?>
                  <?php elseif ($slot['slot_state'] === 'booked'): ?>
                    <span class="badge badge-bad" title="Booked"><?= icon('x', 12) ?></span>
                  <?php else: ?>
                    <span class="badge badge-muted" title="<?= e($slot['block_reason'] ?: label($slot['slot_state'])) ?>">
                      <?= icon('warning', 12) ?>
                    </span>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="legend mb-3">
    <span><span class="badge badge-ok"><?= icon('check', 11) ?></span> Available</span>
    <span><span class="badge badge-bad"><?= icon('x', 11) ?></span> Booked</span>
    <span><span class="badge badge-muted"><?= icon('warning', 11) ?></span> Blocked / maintenance</span>
  </div>

  <!-- Per-facility summary cards -->
  <div class="grid grid-3">
    <?php foreach ($grid as $entry): ?>
      <?php
        $facility = $entry['facility'];
        $open = 0;
        foreach ($entry['slots'] as $slot) {
            if ($slot['slot_state'] === 'available') { $open++; }
        }
      ?>
      <div class="card">
        <div class="row-between mb-1">
          <div class="row" style="gap:9px">
            <span class="thumb" style="width:38px;height:38px">
              <?= icon($isHall ? 'hall' : 'court', 18) ?>
            </span>
            <div>
              <strong style="display:block"><?= e($facility['name']) ?></strong>
              <span class="tiny muted"><?= money($facility['rate']) ?>/hr</span>
            </div>
          </div>
          <span class="badge badge-<?= status_badge($facility['status']) ?>"><?= e(label($facility['status'])) ?></span>
        </div>

        <div class="row-between">
          <span class="small muted">
            <?= $open ?> of <?= count($entry['slots']) ?> <?= pluralise(count($entry['slots']), 'slot') ?> open
          </span>
          <?php if (Auth::check() && !Auth::isStaff() && $open > 0 && $facility['status'] === 'available'): ?>
            <a href="<?= url(($isHall ? '/reserve/hall' : '/reserve/court') . '?facility=' . $facility['id'] . '&date=' . e($date)) ?>"
               class="btn btn-sm btn-primary">Book</a>
          <?php elseif (!Auth::check()): ?>
            <a href="<?= url('/login') ?>" class="btn btn-sm btn-outline">Log in to book</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
