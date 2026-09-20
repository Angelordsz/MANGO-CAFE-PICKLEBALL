<?php
use App\Core\View;

echo View::partial('admin.partials.report_head', [
    'reportTitle' => 'Payment report',
    'reportPath'  => '/admin/reports/payment',
    'from'        => $from,
    'to'          => $to,
    'query'       => ['mode' => $mode],
    'extra'       => '
      <div class="field">
        <label for="mode">Report type</label>
        <select id="mode" name="mode" class="select">
          <option value="summary"' . ($mode === 'summary' ? ' selected' : '') . '>Summary</option>
          <option value="detailed"' . ($mode === 'detailed' ? ' selected' : '') . '>Detailed</option>
        </select>
      </div>',
]);

$grand = 0;
foreach ($summary as $row) {
    $grand += (float) $row['total'];
}
?>

<div class="alert alert-info mb-3 no-print">
  <?= icon('info', 18) ?>
  <span>
    All amounts were received through Mango Drive Café's existing payment channels and
    recorded by staff. The application does not process online payments.
  </span>
</div>

<div class="grid grid-4 mb-3">
  <div class="stat stat-accent">
    <p class="stat-label">Total collected</p>
    <p class="stat-value" style="font-size:1.35rem"><?= money($grand) ?></p>
  </div>
  <?php foreach (array_slice($summary, 0, 3) as $row): ?>
    <div class="stat">
      <p class="stat-label"><?= e(strtoupper($row['payment_method'])) ?></p>
      <p class="stat-value" style="font-size:1.35rem"><?= money($row['total']) ?></p>
      <p class="stat-sub"><?= (int) $row['txn_count'] ?> <?= pluralise((int) $row['txn_count'], 'txn') ?></p>
    </div>
  <?php endforeach; ?>
</div>

<div class="card card-flush">
  <div class="card-head">
    <h2><?= $mode === 'detailed' ? 'Detailed transactions' : 'Summary by day and method' ?></h2>
    <span class="badge badge-muted"><?= count($rows) ?> <?= pluralise(count($rows), 'row') ?></span>
  </div>

  <?php if (!$rows): ?>
    <div class="empty">
      <span class="empty-icon"><?= icon('money', 26) ?></span>
      <h3>No payments in this range</h3>
    </div>
  <?php elseif ($mode === 'detailed'): ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Receipt</th><th>Date</th><th>Player</th><th>For</th><th>Method</th>
            <th>Reference</th><th class="text-right">Due</th><th class="text-right">Paid</th>
            <th>Status</th><th>Recorded by</th><th>Verified by</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><code class="tiny"><?= e($row['payment_code']) ?></code></td>
              <td class="small nowrap"><?= e(fdate($row['payment_date'], 'M j, g:i A')) ?></td>
              <td class="small"><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?: '—' ?></td>
              <td class="small muted"><?= e($row['payable_label'] ?? label($row['payable_type'])) ?></td>
              <td><span class="badge badge-muted"><?= e(strtoupper($row['payment_method'])) ?></span></td>
              <td class="tiny muted"><?= e($row['reference_no'] ?? '—') ?></td>
              <td class="text-right"><?= money($row['amount_due']) ?></td>
              <td class="text-right strong"><?= money($row['amount']) ?></td>
              <td><span class="badge badge-<?= status_badge($row['payment_status']) ?>"><?= e(label($row['payment_status'])) ?></span></td>
              <td class="tiny muted"><?= e($row['recorded_by_name'] ?? '—') ?></td>
              <td class="tiny muted"><?= e($row['verified_by_name'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Date</th><th>Method</th><th>For</th>
            <th class="text-right">Transactions</th><th class="text-right">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php $running = 0; foreach ($rows as $row): $running += (float) $row['total']; ?>
            <tr>
              <td class="small nowrap"><?= e(fdate($row['day'], 'D, M j')) ?></td>
              <td><span class="badge badge-muted"><?= e(strtoupper($row['payment_method'])) ?></span></td>
              <td class="small muted"><?= e(label($row['payable_type'])) ?></td>
              <td class="text-right"><?= (int) $row['txn_count'] ?></td>
              <td class="text-right strong nowrap"><?= money($row['total']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="border-top:2px solid var(--border-strong)">
            <th colspan="4" class="text-right">Grand total</th>
            <th class="text-right"><?= money($running) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="row-between mt-3" style="gap:40px">
  <div class="grow">
    <hr style="margin-bottom:4px">
    <p class="tiny muted" style="margin:0">Prepared by</p>
  </div>
  <div class="grow">
    <hr style="margin-bottom:4px">
    <p class="tiny muted" style="margin:0">Verified by</p>
  </div>
</div>
