<?php
use App\Core\Auth;

$maxValue = max(1, max(array_column($series, 'value')));
?>

<div class="row-between mb-2">
  <div>
    <h1 style="margin:0">Dashboard</h1>
    <p class="small muted" style="margin:0"><?= e(date('l, F j, Y')) ?></p>
  </div>
  <div class="btn-group no-print">
    <a href="<?= url('/admin/reservations/create') ?>" class="btn btn-outline btn-sm">
      <?= icon('plus', 15) ?> Walk-in booking
    </a>
    <a href="<?= url('/admin/players/walk-in') ?>" class="btn btn-primary btn-sm">
      <?= icon('user', 15) ?> Walk-in player
    </a>
  </div>
</div>

<!-- Key numbers -->
<div class="grid grid-4 mb-3">
  <a href="<?= url('/admin/reservations?status=pending') ?>" class="stat stat-accent card-link">
    <p class="stat-label">Pending requests</p>
    <p class="stat-value"><?= (int) $stats['pending'] ?></p>
    <p class="stat-sub">Awaiting your approval</p>
  </a>
  <div class="stat">
    <p class="stat-label">Today's bookings</p>
    <p class="stat-value"><?= (int) $stats['today_bookings'] ?></p>
    <p class="stat-sub"><?= count($today) ?> on the schedule</p>
  </div>
  <div class="stat">
    <p class="stat-label">Today's collections</p>
    <p class="stat-value" style="font-size:1.35rem"><?= money($stats['today_revenue']) ?></p>
    <p class="stat-sub"><?= money($stats['month_revenue']) ?> last 30 days</p>
  </div>
  <div class="stat">
    <p class="stat-label">Active players</p>
    <p class="stat-value"><?= (int) $stats['players'] ?></p>
    <p class="stat-sub">+<?= (int) $stats['new_players'] ?> today</p>
  </div>
</div>

<div class="grid grid-2 mb-3">
  <!-- Pending approvals -->
  <div class="card card-flush">
    <div class="card-head">
      <h2>Pending approvals</h2>
      <a href="<?= url('/admin/reservations?status=pending') ?>" class="small strong">View all</a>
    </div>
    <div class="card-body" style="padding-top:4px">
      <?php if (!$pending): ?>
        <div class="empty" style="padding:24px 12px">
          <span class="empty-icon"><?= icon('check', 22) ?></span>
          <p class="small" style="margin:0">Nothing waiting. Good work.</p>
        </div>
      <?php else: ?>
        <?php foreach ($pending as $request): ?>
          <div class="list-item">
            <span class="thumb" style="width:38px;height:38px">
              <?= icon($request['reservation_type'] === 'court' ? 'court' : 'hall', 17) ?>
            </span>
            <div class="grow">
              <strong class="small" style="display:block">
                <?= e(trim($request['first_name'] . ' ' . $request['last_name'])) ?>
              </strong>
              <span class="tiny muted">
                <?= e($request['facility_name']) ?> ·
                <?= e(human_date($request['reservation_date'])) ?> ·
                <?= e(ftime($request['start_time'])) ?>
              </span>
            </div>
            <a href="<?= url('/admin/reservations/' . (int) $request['id']) ?>" class="btn btn-sm btn-primary">
              Review
            </a>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Bookings chart -->
  <div class="card">
    <div class="row-between mb-1">
      <h2 style="margin:0">Bookings, last 14 days</h2>
      <span class="badge badge-muted"><?= array_sum(array_column($series, 'value')) ?> total</span>
    </div>

    <div class="chart" role="img"
         aria-label="Bar chart of reservations per day over the last 14 days">
      <?php foreach ($series as $point): ?>
        <div class="chart-col" title="<?= e(fdate($point['day'])) ?>: <?= $point['value'] ?>">
          <span class="chart-value"><?= $point['value'] ?: '' ?></span>
          <div class="chart-bar" style="height:<?= max(3, round(100 * $point['value'] / $maxValue)) ?>%"></div>
          <span class="chart-label"><?= e($point['label']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Today's schedule -->
<div class="card card-flush mb-3">
  <div class="card-head">
    <h2>Today's schedule</h2>
    <a href="<?= url('/admin/reservations?date=' . date('Y-m-d')) ?>" class="small strong">Manage</a>
  </div>

  <?php if (!$today): ?>
    <div class="empty" style="padding:28px">
      <span class="empty-icon"><?= icon('calendar', 22) ?></span>
      <h3>Nothing booked today</h3>
      <p class="small" style="margin:0">The courts are free.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Time</th>
            <th>Facility</th>
            <th>Customer</th>
            <th>Contact</th>
            <th>Status</th>
            <th class="text-right">Amount</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($today as $booking): ?>
            <tr>
              <td class="nowrap strong"><?= e(ftimerange($booking['start_time'], $booking['end_time'])) ?></td>
              <td><?= e($booking['facility_name']) ?></td>
              <td><?= e(trim($booking['first_name'] . ' ' . $booking['last_name'])) ?: '—' ?></td>
              <td class="small muted"><?= e($booking['phone_number'] ?? '—') ?></td>
              <td><span class="badge badge-<?= status_badge($booking['status']) ?>"><?= e(label($booking['status'])) ?></span></td>
              <td class="text-right"><?= money($booking['total_amount']) ?></td>
              <td class="text-right">
                <a href="<?= url('/admin/reservations/' . (int) $booking['id']) ?>" class="btn btn-sm btn-ghost">
                  <?= icon('chevron-right', 15) ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="grid grid-2">
  <!-- Utilisation -->
  <div class="card">
    <h2 class="mb-2">Facility use, last 7 days</h2>

    <?php if (!$utilisation): ?>
      <p class="small muted">No schedule data yet. Generate slots under Facility schedules.</p>
    <?php else: ?>
      <?php foreach ($utilisation as $facility): ?>
        <?php $pct = $facility['slots'] > 0 ? round(100 * $facility['booked'] / $facility['slots']) : 0; ?>
        <div class="mb-2">
          <div class="row-between small mb-1">
            <strong><?= e($facility['name'] ?? 'Unknown') ?></strong>
            <span class="muted"><?= (int) $facility['booked'] ?>/<?= (int) $facility['slots'] ?> · <?= $pct ?>%</span>
          </div>
          <div class="step-bar" style="background:var(--border)">
            <div style="height:100%;width:<?= $pct ?>%;background:var(--accent);border-radius:2px"></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Attention needed -->
  <div class="card">
    <h2 class="mb-2">Needs attention</h2>

    <div class="list-item">
      <span class="thumb" style="width:36px;height:36px"><?= icon('money', 16) ?></span>
      <div class="grow">
        <strong class="small" style="display:block">Unverified payments</strong>
        <span class="tiny muted">Recorded but not yet verified</span>
      </div>
      <?php if (Auth::isAdmin()): ?>
        <a href="<?= url('/admin/payments?status=paid') ?>" class="badge badge-<?= $stats['unverified_payments'] > 0 ? 'warn' : 'muted' ?>">
          <?= (int) $stats['unverified_payments'] ?>
        </a>
      <?php else: ?>
        <span class="badge badge-muted"><?= (int) $stats['unverified_payments'] ?></span>
      <?php endif; ?>
    </div>

    <div class="list-item">
      <span class="thumb" style="width:36px;height:36px"><?= icon('shuffle', 16) ?></span>
      <div class="grow">
        <strong class="small" style="display:block">Open matching sessions</strong>
        <span class="tiny muted">Ready to lock and generate</span>
      </div>
      <a href="<?= url('/admin/matching') ?>" class="badge badge-<?= $stats['open_pools'] > 0 ? 'info' : 'muted' ?>">
        <?= (int) $stats['open_pools'] ?>
      </a>
    </div>

    <div class="list-item">
      <span class="thumb" style="width:36px;height:36px"><?= icon('trophy', 16) ?></span>
      <div class="grow">
        <strong class="small" style="display:block">Active events</strong>
        <span class="tiny muted">Upcoming or running now</span>
      </div>
      <a href="<?= url('/admin/events') ?>" class="badge badge-<?= $stats['active_events'] > 0 ? 'info' : 'muted' ?>">
        <?= (int) $stats['active_events'] ?>
      </a>
    </div>
  </div>
</div>
