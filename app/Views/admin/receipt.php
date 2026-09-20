<div class="row-between mb-2 no-print">
  <a href="<?= url('/admin/payments') ?>" class="btn btn-ghost btn-sm">
    <?= icon('arrow-left', 15) ?> Back to payments
  </a>
  <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
    <?= icon('print', 15) ?> Print receipt
  </button>
</div>

<div class="receipt">
  <h2 style="margin:0 0 2px">MANGO DRIVE CAFE</h2>
  <p style="text-align:center;font-size:.78rem;margin:0 0 14px">
    Pickleball Courts &amp; Function Hall
  </p>

  <hr style="border-top:1px dashed #999;margin:12px 0">

  <p style="margin:0 0 3px"><strong>OFFICIAL RECEIPT</strong></p>
  <p style="margin:0 0 12px">No. <?= e($payment['payment_code']) ?></p>

  <p style="margin:0 0 3px">Date: <?= e(fdate($payment['payment_date'], 'M j, Y g:i A')) ?></p>
  <p style="margin:0 0 12px">
    Received from:
    <?= e(trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? ''))) ?: 'Walk-in customer' ?>
  </p>

  <hr style="border-top:1px dashed #999;margin:12px 0">

  <?php if ($reservation): ?>
    <p style="margin:0 0 3px">For: <?= e(label($reservation['reservation_type'])) ?> reservation</p>
    <p style="margin:0 0 3px">Ref: <?= e($reservation['reservation_code']) ?></p>
    <p style="margin:0 0 3px">Facility: <?= e($reservation['facility_name']) ?></p>
    <p style="margin:0 0 12px">
      <?= e(fdate($reservation['reservation_date'])) ?>,
      <?= e(ftimerange($reservation['start_time'], $reservation['end_time'])) ?>
    </p>
  <?php else: ?>
    <p style="margin:0 0 12px">For: <?= e(label($payment['payable_type'])) ?></p>
  <?php endif; ?>

  <hr style="border-top:1px dashed #999;margin:12px 0">

  <div style="display:flex;justify-content:space-between;margin-bottom:4px">
    <span>Amount due</span><span><?= money($payment['amount_due']) ?></span>
  </div>
  <div style="display:flex;justify-content:space-between;margin-bottom:4px">
    <span>Amount paid</span><strong><?= money($payment['amount']) ?></strong>
  </div>
  <div style="display:flex;justify-content:space-between;margin-bottom:4px">
    <span>Method</span><span><?= e(strtoupper($payment['payment_method'])) ?></span>
  </div>
  <?php if ($payment['reference_no']): ?>
    <div style="display:flex;justify-content:space-between;margin-bottom:4px">
      <span>Reference</span><span><?= e($payment['reference_no']) ?></span>
    </div>
  <?php endif; ?>
  <div style="display:flex;justify-content:space-between">
    <span>Status</span><span><?= e(strtoupper($payment['payment_status'])) ?></span>
  </div>

  <hr style="border-top:1px dashed #999;margin:12px 0">

  <p style="margin:0 0 3px;font-size:.78rem">Recorded by: <?= e($payment['recorded_by_name'] ?? '—') ?></p>
  <?php if ($payment['verified_by_name']): ?>
    <p style="margin:0 0 3px;font-size:.78rem">Verified by: <?= e($payment['verified_by_name']) ?></p>
  <?php endif; ?>

  <p style="text-align:center;font-size:.72rem;margin:16px 0 0">
    Thank you for playing at Mango Drive Cafe!
  </p>
</div>
