<?php
use App\Core\View;

echo View::partial('admin.partials.report_head', [
    'reportTitle' => 'Facility utilisation report',
    'reportPath'  => '/admin/reports/utilisation',
    'from'        => $from,
    'to'          => $to,
]);

$maxHour = 1;
foreach ($byHour as $h) { $maxHour = max($maxHour, (int) $h['slots']); }
?>

<div class="card card-flush mb-3">
  <div class="card-head"><h2>By facility</h2></div>

  <?php if (!$rows): ?>
    <div class="empty">
      <span class="empty-icon"><?= icon('chart', 26) ?></span>
      <h3>No schedule data in this range</h3>
      <p class="small">Generate time slots first under Facility schedules.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Facility</th><th>Type</th>
            <th class="text-right">Offered</th><th class="text-right">Booked</th>
            <th class="text-right">Blocked</th><th>Utilisation</th>
            <th class="text-right">Booked value</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><strong class="small"><?= e($row['facility_name'] ?? '—') ?></strong></td>
              <td><span class="badge badge-muted"><?= e(label($row['facility_type'])) ?></span></td>
              <td class="text-right"><?= (int) $row['slots_offered'] ?></td>
              <td class="text-right strong"><?= (int) $row['slots_booked'] ?></td>
              <td class="text-right muted"><?= (int) $row['slots_blocked'] ?></td>
              <td style="min-width:130px">
                <div class="row" style="gap:8px">
                  <div class="step-bar grow" style="background:var(--border);margin:0">
                    <div style="height:100%;width:<?= (float) $row['utilisation_pct'] ?>%;background:var(--accent);border-radius:2px"></div>
                  </div>
                  <span class="tiny strong"><?= (float) $row['utilisation_pct'] ?>%</span>
                </div>
              </td>
              <td class="text-right nowrap"><?= money($row['booked_value']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($byHour): ?>
  <div class="card">
    <h2 class="mb-1">Busiest hours</h2>
    <p class="small muted mb-2">Booked slots per hour of the day across the range.</p>

    <div class="chart" role="img" aria-label="Bar chart of booked slots by hour of day">
      <?php foreach ($byHour as $hour): ?>
        <?php $pct = round(100 * (int) $hour['booked'] / $maxHour); ?>
        <div class="chart-col" title="<?= (int) $hour['booked'] ?> of <?= (int) $hour['slots'] ?> booked">
          <span class="chart-value"><?= (int) $hour['booked'] ?: '' ?></span>
          <div class="chart-bar" style="height:<?= max(3, $pct) ?>%"></div>
          <span class="chart-label"><?= date('ga', mktime((int) $hour['hour'], 0)) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
