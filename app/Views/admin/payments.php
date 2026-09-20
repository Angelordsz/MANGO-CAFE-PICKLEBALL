<?php use App\Core\Auth; ?>

<div class="row-between mb-2">
  <div>
    <h1 style="margin:0">Payments</h1>
    <p class="small muted" style="margin:0">
      Recorded at the counter. No online payments are processed by this application.
    </p>
  </div>
  <a href="<?= url('/admin/reports/payment?from=' . e($filters['from']) . '&to=' . e($filters['to'])) ?>"
     class="btn btn-outline btn-sm no-print">
    <?= icon('clipboard', 15) ?> Payment report
  </a>
</div>

<div class="grid grid-3 mb-2">
  <div class="stat stat-accent">
    <p class="stat-label">Collected in range</p>
    <p class="stat-value" style="font-size:1.4rem"><?= money($totals['amount']) ?></p>
    <p class="stat-sub"><?= $totals['count'] ?> <?= pluralise($totals['count'], 'transaction') ?></p>
  </div>

  <?php foreach (array_slice($summary, 0, 2) as $row): ?>
    <div class="stat">
      <p class="stat-label"><?= e(strtoupper($row['payment_method'])) ?></p>
      <p class="stat-value" style="font-size:1.4rem"><?= money($row['total']) ?></p>
      <p class="stat-sub"><?= (int) $row['txn_count'] ?> <?= pluralise((int) $row['txn_count'], 'payment') ?></p>
    </div>
  <?php endforeach; ?>
</div>

<form method="get" action="<?= url('/admin/payments') ?>" class="filters no-print" data-auto-filter>
  <div class="field">
    <label for="from">From</label>
    <input type="date" id="from" name="from" class="input" value="<?= e($filters['from']) ?>">
  </div>
  <div class="field">
    <label for="to">To</label>
    <input type="date" id="to" name="to" class="input" value="<?= e($filters['to']) ?>">
  </div>
  <div class="field">
    <label for="method">Method</label>
    <select id="method" name="method" class="select">
      <option value="">All methods</option>
      <?php foreach (['cash','gcash','maya','bank_transfer','card','other'] as $m): ?>
        <option value="<?= $m ?>" <?= $filters['method'] === $m ? 'selected' : '' ?>><?= e(label($m)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status" class="select">
      <option value="">All statuses</option>
      <?php foreach (['unpaid','partial','paid','verified','refunded','void'] as $s): ?>
        <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(label($s)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="payable_type">For</label>
    <select id="payable_type" name="payable_type" class="select">
      <option value="">Everything</option>
      <option value="reservation"       <?= $filters['payable_type'] === 'reservation' ? 'selected' : '' ?>>Reservations</option>
      <option value="event_participant" <?= $filters['payable_type'] === 'event_participant' ? 'selected' : '' ?>>Events</option>
    </select>
  </div>
  <div class="field" style="flex:0 0 auto">
    <button type="submit" class="btn btn-outline"><?= icon('filter', 15) ?> Filter</button>
  </div>
</form>

<div class="card card-flush">
  <?php if (!$payments): ?>
    <div class="empty">
      <span class="empty-icon"><?= icon('money', 26) ?></span>
      <h3>No payments in this range</h3>
      <p class="small">Payments are recorded from a reservation or an event.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Receipt</th><th>Date</th><th>Player</th><th>For</th>
            <th>Method</th><th>Ref</th><th class="text-right">Amount</th>
            <th>Status</th><th>Recorded by</th><th class="no-print"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $payment): ?>
            <tr>
              <td><code class="tiny"><?= e($payment['payment_code']) ?></code></td>
              <td class="small nowrap"><?= e(fdate($payment['payment_date'], 'M j, g:i A')) ?></td>
              <td class="small">
                <?= e(trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? ''))) ?: '—' ?>
              </td>
              <td class="small muted"><?= e($payment['payable_label'] ?? label($payment['payable_type'])) ?></td>
              <td><span class="badge badge-muted"><?= e(strtoupper($payment['payment_method'])) ?></span></td>
              <td class="tiny muted"><?= e($payment['reference_no'] ?? '—') ?></td>
              <td class="text-right strong nowrap"><?= money($payment['amount']) ?></td>
              <td>
                <span class="badge badge-<?= status_badge($payment['payment_status']) ?>">
                  <?= e(label($payment['payment_status'])) ?>
                </span>
              </td>
              <td class="tiny muted"><?= e($payment['recorded_by_name'] ?? '—') ?></td>
              <td class="text-right no-print nowrap">
                <a href="<?= url('/admin/payments/' . (int) $payment['id'] . '/receipt') ?>"
                   class="btn btn-sm btn-ghost" title="Receipt"><?= icon('print', 14) ?></a>

                <?php if (Auth::isAdmin() && in_array($payment['payment_status'], ['paid','partial'], true)): ?>
                  <form method="post" action="<?= url('/admin/payments/' . (int) $payment['id'] . '/verify') ?>"
                        style="display:inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-ghost" title="Verify"><?= icon('check', 14) ?></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if (!Auth::isAdmin()): ?>
  <p class="field-help mt-2">
    <?= icon('info', 12) ?>
    Staff can record payments; only an administrator can verify them.
  </p>
<?php endif; ?>
