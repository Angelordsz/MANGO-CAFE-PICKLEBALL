<?php
use App\Core\Auth;

$r    = $reservation;
$paid = (float) $r['amount_paid'];
$due  = (float) $r['total_amount'] - $paid;
?>

<div class="row mb-2" style="gap:10px">
  <a href="<?= url('/admin/reservations') ?>" class="icon-btn no-print"
     style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div class="grow">
    <h1 style="margin:0"><?= e($r['reservation_code']) ?></h1>
    <p class="small muted" style="margin:0">
      Submitted <?= e(fdate($r['created_at'], 'M j, Y g:i A')) ?> · <?= e(label($r['source'])) ?>
    </p>
  </div>
  <span class="badge badge-<?= status_badge($r['status']) ?>"><?= e(label($r['status'])) ?></span>
</div>

<!-- A9: Approve / Reject -->
<?php if ($r['status'] === 'pending'): ?>
  <div class="card card-lg mb-3" style="border-color:var(--accent)">
    <h2 class="mb-1">Review this request</h2>
    <p class="small muted mb-2">
      The time slot is already held. Approving notifies the customer; rejecting releases the slot.
    </p>

    <div class="field">
      <label for="admin_remarks">Remarks <span class="label-hint">(required when rejecting)</span></label>
      <input type="text" id="admin_remarks" class="input" maxlength="255"
             placeholder="e.g. Court reserved for maintenance that day">
    </div>

    <div class="btn-group">
      <form method="post" action="<?= url('/admin/reservations/' . (int) $r['id'] . '/approve') ?>"
            onsubmit="this.remarks.value = document.getElementById('admin_remarks').value">
        <?= csrf_field() ?>
        <input type="hidden" name="admin_remarks">
        <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> Approve</button>
      </form>

      <form method="post" action="<?= url('/admin/reservations/' . (int) $r['id'] . '/reject') ?>"
            onsubmit="this.admin_remarks.value = document.getElementById('admin_remarks').value">
        <?= csrf_field() ?>
        <input type="hidden" name="admin_remarks">
        <button type="submit" class="btn btn-danger"
                data-confirm="Reject this request and release the slot?">
          <?= icon('x', 16) ?> Reject
        </button>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="grid grid-2 mb-3">
  <!-- Booking details -->
  <div class="card">
    <h2 class="mb-2">Booking</h2>
    <div class="summary">
      <div class="summary-row">
        <span class="muted">Facility</span>
        <strong><?= e($r['facility_name']) ?> <span class="tiny muted"><?= e($r['facility_code']) ?></span></strong>
      </div>
      <div class="summary-row">
        <span class="muted">Type</span>
        <strong><?= e(label($r['reservation_type'])) ?></strong>
      </div>
      <div class="summary-row">
        <span class="muted">Date</span>
        <strong><?= e(fdate($r['reservation_date'], 'l, F j, Y')) ?></strong>
      </div>
      <div class="summary-row">
        <span class="muted">Time</span>
        <strong><?= e(ftimerange($r['start_time'], $r['end_time'])) ?></strong>
      </div>
      <div class="summary-row">
        <span class="muted">Duration</span>
        <strong><?= (int) $r['duration_hours'] ?> <?= pluralise((int) $r['duration_hours'], 'hour') ?></strong>
      </div>
      <div class="summary-row">
        <span class="muted">Party size</span>
        <strong><?= (int) $r['party_size'] ?></strong>
      </div>
      <?php if ($r['purpose']): ?>
        <div class="summary-row">
          <span class="muted">Purpose</span>
          <strong><?= e($r['purpose']) ?></strong>
        </div>
      <?php endif; ?>
      <div class="summary-row summary-total">
        <span>Total</span>
        <span><?= money($r['total_amount']) ?></span>
      </div>
    </div>

    <?php if ($r['customer_notes']): ?>
      <p class="label mt-2">Customer notes</p>
      <p class="small muted" style="margin:0"><?= e($r['customer_notes']) ?></p>
    <?php endif; ?>

    <?php if ($r['admin_remarks']): ?>
      <p class="label mt-2">Admin remarks</p>
      <p class="small muted" style="margin:0"><?= e($r['admin_remarks']) ?></p>
    <?php endif; ?>
  </div>

  <!-- Customer -->
  <div class="card">
    <h2 class="mb-2">Customer</h2>

    <div class="row mb-2" style="gap:12px">
      <span class="avatar avatar-lg" style="width:54px;height:54px;font-size:1.1rem">
        <?= e(initials($r['first_name'] ?? '?', $r['last_name'] ?? '')) ?>
      </span>
      <div class="grow">
        <strong style="display:block"><?= e(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))) ?: 'Unknown' ?></strong>
        <span class="small muted"><?= e($r['player_code'] ?? '') ?></span>
      </div>
      <?php if ($r['player_id']): ?>
        <a href="<?= url('/admin/players/' . (int) $r['player_id']) ?>" class="btn btn-sm btn-outline no-print">Record</a>
      <?php endif; ?>
    </div>

    <div class="summary">
      <div class="summary-row">
        <span class="muted">Phone</span>
        <strong><?= e($r['phone_number'] ?? '—') ?></strong>
      </div>
      <div class="summary-row">
        <span class="muted">Email</span>
        <strong class="small"><?= e($r['player_email'] ?? $r['account_email'] ?? '—') ?></strong>
      </div>
      <div class="summary-row">
        <span class="muted">Account</span>
        <strong><?= $r['username'] ? e($r['username']) : 'Walk-in (no account)' ?></strong>
      </div>
      <?php if ($r['reviewed_by_name']): ?>
        <div class="summary-row">
          <span class="muted">Reviewed by</span>
          <strong><?= e($r['reviewed_by_name']) ?> · <?= e(fdate($r['reviewed_at'], 'M j, g:i A')) ?></strong>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($history): ?>
      <p class="label mt-2">Recent activity</p>
      <?php foreach ($history as $entry): ?>
        <p class="tiny muted" style="margin:0 0 4px">
          <?= e(fdate($entry['activity_date'], 'M j')) ?> — <?= e($entry['description']) ?>
        </p>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Payments (record-only) -->
<div class="card mb-3">
  <div class="row-between mb-2">
    <h2 style="margin:0">Payments</h2>
    <span class="badge badge-<?= $due <= 0 && $paid > 0 ? 'ok' : ($paid > 0 ? 'warn' : 'muted') ?>">
      <?= $due <= 0 && $paid > 0 ? 'Settled' : ($paid > 0 ? money($due) . ' due' : 'Unpaid') ?>
    </span>
  </div>

  <?php if ($payments): ?>
    <div class="table-wrap mb-2">
      <table class="table table-compact">
        <thead>
          <tr>
            <th>Receipt</th><th>Date</th><th>Method</th><th>Ref</th>
            <th class="text-right">Amount</th><th>Status</th><th>Recorded by</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $payment): ?>
            <tr>
              <td><code class="tiny"><?= e($payment['payment_code']) ?></code></td>
              <td class="small nowrap"><?= e(fdate($payment['payment_date'], 'M j, g:i A')) ?></td>
              <td><span class="badge badge-muted"><?= e(strtoupper($payment['payment_method'])) ?></span></td>
              <td class="tiny muted"><?= e($payment['reference_no'] ?? '—') ?></td>
              <td class="text-right strong"><?= money($payment['amount']) ?></td>
              <td><span class="badge badge-<?= status_badge($payment['payment_status']) ?>"><?= e(label($payment['payment_status'])) ?></span></td>
              <td class="tiny muted"><?= e($payment['recorded_by_name'] ?? '—') ?></td>
              <td class="text-right no-print">
                <?php if (Auth::isAdmin() && in_array($payment['payment_status'], ['paid','partial'], true)): ?>
                  <form method="post" action="<?= url('/admin/payments/' . (int) $payment['id'] . '/verify') ?>">
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
  <?php else: ?>
    <p class="small muted mb-2">No payment recorded yet.</p>
  <?php endif; ?>

  <?php if ($due > 0 && !in_array($r['status'], ['cancelled','rejected'], true)): ?>
    <form method="post" action="<?= url('/admin/payments') ?>" class="no-print" data-guard>
      <?= csrf_field() ?>
      <input type="hidden" name="payable_type" value="reservation">
      <input type="hidden" name="payable_id" value="<?= (int) $r['id'] ?>">
      <input type="hidden" name="player_id" value="<?= (int) $r['player_id'] ?>">
      <input type="hidden" name="amount_due" value="<?= e($r['total_amount']) ?>">
      <input type="hidden" name="redirect" value="/admin/reservations/<?= (int) $r['id'] ?>">

      <p class="label">Record a payment taken at the counter</p>

      <div class="row row-wrap" style="gap:8px;align-items:flex-end">
        <div class="field" style="margin:0;flex:1 1 130px">
          <label for="amount">Amount received</label>
          <input type="number" id="amount" name="amount" class="input" step="0.01" min="0.01"
                 max="999999" value="<?= e(number_format($due, 2, '.', '')) ?>" required>
        </div>

        <div class="field" style="margin:0;flex:1 1 130px">
          <label for="payment_method">Method</label>
          <select id="payment_method" name="payment_method" class="select">
            <option value="cash">Cash</option>
            <option value="gcash">GCash</option>
            <option value="maya">Maya</option>
            <option value="bank_transfer">Bank transfer</option>
            <option value="card">Card</option>
          </select>
        </div>

        <div class="field" style="margin:0;flex:1 1 150px">
          <label for="reference_no">Reference no.</label>
          <input type="text" id="reference_no" name="reference_no" class="input" maxlength="60"
                 placeholder="GCash ref (optional)">
        </div>

        <button type="submit" class="btn btn-primary"><?= icon('money', 15) ?> Record</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<!-- A10: Cancel / Reschedule / Complete -->
<?php if (in_array($r['status'], ['pending','approved'], true)): ?>
  <div class="card no-print">
    <h2 class="mb-2">Actions</h2>

    <div class="btn-group mb-2">
      <a href="<?= url('/admin/reservations/' . (int) $r['id'] . '/reschedule') ?>" class="btn btn-outline">
        <?= icon('calendar', 15) ?> Reschedule
      </a>

      <?php if ($r['status'] === 'approved'): ?>
        <form method="post" action="<?= url('/admin/reservations/' . (int) $r['id'] . '/complete') ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline"><?= icon('check', 15) ?> Mark completed</button>
        </form>
      <?php endif; ?>
    </div>

    <form method="post" action="<?= url('/admin/reservations/' . (int) $r['id'] . '/cancel') ?>" data-guard>
      <?= csrf_field() ?>
      <div class="row" style="gap:8px;align-items:flex-end">
        <div class="field grow" style="margin:0">
          <label for="cancel_reason">Cancellation reason</label>
          <input type="text" id="cancel_reason" name="cancel_reason" class="input" maxlength="255"
                 placeholder="Reason shown to the customer" required>
        </div>
        <button type="submit" class="btn btn-danger"
                data-confirm="Cancel this reservation and release the slot?">
          Cancel booking
        </button>
      </div>
    </form>
  </div>
<?php endif; ?>
