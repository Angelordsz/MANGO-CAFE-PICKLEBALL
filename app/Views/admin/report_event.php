<?php
use App\Core\View;

$statusOptions = '';
foreach (['draft','upcoming','active','ongoing','completed','cancelled'] as $s) {
    $statusOptions .= '<option value="' . $s . '"' . ($status === $s ? ' selected' : '') . '>' . e(label($s)) . '</option>';
}

echo View::partial('admin.partials.report_head', [
    'reportTitle' => 'Event report',
    'reportPath'  => '/admin/reports/event',
    'from'        => $from,
    'to'          => $to,
    'query'       => ['status' => $status],
    'extra'       => '
      <div class="field">
        <label for="status">Event status</label>
        <select id="status" name="status" class="select">
          <option value="">All statuses</option>' . $statusOptions . '
        </select>
      </div>',
]);
?>

<div class="grid grid-4 mb-3">
  <div class="stat stat-accent">
    <p class="stat-label">Events</p>
    <p class="stat-value"><?= (int) $totals['events'] ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Registrations</p>
    <p class="stat-value"><?= (int) $totals['registered'] ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Expected revenue</p>
    <p class="stat-value" style="font-size:1.3rem"><?= money($totals['expected']) ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Collected</p>
    <p class="stat-value" style="font-size:1.3rem;color:var(--ok)"><?= money($totals['collected']) ?></p>
  </div>
</div>

<div class="card card-flush">
  <?php if (!$rows): ?>
    <div class="empty">
      <span class="empty-icon"><?= icon('trophy', 26) ?></span>
      <h3>No events in this range</h3>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Code</th><th>Event</th><th>Type</th><th>Start</th><th>Status</th>
            <th class="text-right">Cap.</th><th class="text-right">Registered</th>
            <th class="text-right">Attended</th><th class="text-right">Fill</th>
            <th class="text-right">Expected</th><th class="text-right">Collected</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <?php $fill = (int) $row['capacity'] > 0 ? round(100 * (int) $row['registered'] / (int) $row['capacity']) : 0; ?>
            <tr>
              <td><code class="tiny"><?= e($row['event_code']) ?></code></td>
              <td><strong class="small"><?= e($row['title']) ?></strong></td>
              <td><span class="badge badge-accent"><?= e(label($row['event_type'])) ?></span></td>
              <td class="small nowrap"><?= e(fdate($row['start_datetime'], 'M j, g:i A')) ?></td>
              <td><span class="badge badge-<?= status_badge($row['status']) ?>"><?= e(label($row['status'])) ?></span></td>
              <td class="text-right"><?= (int) $row['capacity'] ?></td>
              <td class="text-right strong"><?= (int) $row['registered'] ?></td>
              <td class="text-right"><?= (int) $row['attended'] ?></td>
              <td class="text-right"><?= $fill ?>%</td>
              <td class="text-right nowrap"><?= money($row['expected_revenue']) ?></td>
              <td class="text-right nowrap"><?= money($row['collected_revenue']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="border-top:2px solid var(--border-strong)">
            <th colspan="9" class="text-right">Totals</th>
            <th class="text-right"><?= money($totals['expected']) ?></th>
            <th class="text-right"><?= money($totals['collected']) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>
