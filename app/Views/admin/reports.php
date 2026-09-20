<h1 class="mb-1">Reports</h1>
<p class="muted mb-3">
  Every report covers a date range, prints cleanly and exports to CSV for Excel.
</p>

<div class="grid grid-2">
  <?php
  $reports = [
    ['path' => '/admin/reports/reservation', 'icon' => 'ticket', 'name' => 'Reservation report',
     'desc' => 'Every booking in a range, with status, source, amount and what was collected.',
     'note' => 'Supports Objective 5 — reservation records for management decisions.'],
    ['path' => '/admin/reports/utilisation', 'icon' => 'chart', 'name' => 'Facility utilisation report',
     'desc' => 'Slots offered against slots booked, per court and hall, plus busiest hours.',
     'note' => 'Shows which facilities and hours actually earn.'],
    ['path' => '/admin/reports/event', 'icon' => 'trophy', 'name' => 'Event report',
     'desc' => 'Registrations, attendance and revenue per event, filterable by status.',
     'note' => 'Figure 3.9 — Admin Generate Event Report.'],
    ['path' => '/admin/reports/payment', 'icon' => 'money', 'name' => 'Payment report',
     'desc' => 'Summary by day and method, or a detailed transaction listing.',
     'note' => 'Figure 3.10 — summary and detailed modes.'],
    ['path' => '/admin/reports/player', 'icon' => 'users', 'name' => 'Player activity report',
     'desc' => 'Per-player reservations, matching sign-ups, matches played and amount paid.',
     'note' => 'Distinguishes walk-in records from online accounts.'],
  ];

  foreach ($reports as $report): ?>
    <a href="<?= url($report['path'] . '?from=' . e($from) . '&to=' . e($to)) ?>" class="card card-link">
      <div class="row mb-1" style="gap:10px">
        <span class="empty-icon" style="width:42px;height:42px;margin:0;background:var(--lime-200);color:var(--lime-700)">
          <?= icon($report['icon'], 19) ?>
        </span>
        <h3 style="margin:0"><?= e($report['name']) ?></h3>
      </div>
      <p class="small muted mb-1"><?= e($report['desc']) ?></p>
      <p class="tiny muted" style="margin:0"><?= icon('info', 11) ?> <?= e($report['note']) ?></p>
    </a>
  <?php endforeach; ?>
</div>
