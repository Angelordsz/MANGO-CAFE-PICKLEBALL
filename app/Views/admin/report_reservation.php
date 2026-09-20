<?php
use App\Core\View;

$statusOptions = '';
foreach (['pending','approved','rejected','cancelled','completed','no_show'] as $s) {
    $statusOptions .= '<option value="' . $s . '"' . ($status === $s ? ' selected' : '') . '>' . e(label($s)) . '</option>';
}

$extra = '
  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status" class="select">
      <option value="">All statuses</option>' . $statusOptions . '
    </select>
  </div>
  <div class="field">
    <label for="type">Facility</label>
    <select id="type" name="type" class="select">
      <option value="">All facilities</option>
      <option value="court"' . ($type === 'court' ? ' selected' : '') . '>Courts</option>
      <option value="function_hall"' . ($type === 'function_hall' ? ' selected' : '') . '>Function hall</option>
    </select>
  </div>';

echo View::partial('admin.partials.report_head', [
    'reportTitle' => 'Reservation report',
    'reportPath'  => '/admin/reports/reservation',
    'from'        => $from,
    'to'          => $to,
    'extra'       => $extra,
    'query'       => ['status' => $status, 'type' => $type],
]);
?>

<div class="grid grid-4 mb-3">
  <div class="stat stat-accent">
    <p class="stat-label">Reservations</p>
    <p class="stat-value"><?= (int) $totals['count'] ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Court hours</p>
    <p class="stat-value"><?= number_format((float) $totals['hours'], 1) ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Gross value</p>
    <p class="stat-value" style="font-size:1.3rem"><?= money($totals['gross']) ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Collected</p>
    <p class="stat-value" style="font-size:1.3rem;color:var(--ok)"><?= money($totals['paid']) ?></p>
  </div>
</div>

<div class="card card-flush">
  <?php if (!$rows): ?>
    <div class="empty">
      <span class="empty-icon"><?= icon('ticket', 26) ?></span>
      <h3>No reservations in this range</h3>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Code</th><th>Date</th><th>Time</th><th>Facility</th><th>Customer</th>
            <th>Hours</th><th>Source</th><th>Status</th>
            <th class="text-right">Amount</th><th class="text-right">Paid</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><code class="tiny"><?= e($row['reservation_code']) ?></code></td>
              <td class="small nowrap"><?= e(fdate($row['reservation_date'], 'M j')) ?></td>
              <td class="small nowrap muted"><?= e(ftimerange($row['start_time'], $row['end_time'])) ?></td>
              <td class="small"><?= e($row['facility_name']) ?></td>
              <td class="small">
                <?= e(trim($row['customer'])) ?: '—' ?>
                <span class="tiny muted" style="display:block"><?= e($row['phone_number'] ?? '') ?></span>
              </td>
              <td class="text-center"><?= (float) $row['duration_hours'] ?></td>
              <td><span class="badge badge-muted"><?= e(label($row['source'])) ?></span></td>
              <td><span class="badge badge-<?= status_badge($row['status']) ?>"><?= e(label($row['status'])) ?></span></td>
              <td class="text-right nowrap"><?= money($row['total_amount']) ?></td>
              <td class="text-right nowrap"><?= money($row['amount_paid']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="border-top:2px solid var(--border-strong)">
            <th colspan="8" class="text-right">Totals</th>
            <th class="text-right"><?= money($totals['gross']) ?></th>
            <th class="text-right"><?= money($totals['paid']) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<p class="tiny muted mt-2">
  Prepared by Mango Drive Café reservation system · <?= e(date('Y-m-d H:i')) ?>
</p>
