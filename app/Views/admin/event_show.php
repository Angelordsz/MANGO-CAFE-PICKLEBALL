<?php
$spotsLeft  = max(0, (int) $event['capacity'] - count($participants));
$expected   = 0;
$collected  = 0;
foreach ($participants as $p) {
    $expected  += (float) $p['amount_due'];
    $collected += (float) $p['amount_paid'];
}
?>

<div class="row mb-2" style="gap:10px">
  <a href="<?= url('/admin/events') ?>" class="icon-btn no-print"
     style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div class="grow">
    <h1 style="margin:0"><?= e($event['title']) ?></h1>
    <p class="small muted" style="margin:0">
      <?= e($event['event_code']) ?> · <?= e(label($event['event_type'])) ?> ·
      <?= e(fdate($event['start_datetime'], 'M j, Y g:i A')) ?>
    </p>
  </div>
  <div class="btn-group no-print">
    <span class="badge badge-<?= status_badge($event['status']) ?>"><?= e(label($event['status'])) ?></span>
    <a href="<?= url('/admin/events/' . (int) $event['id'] . '/edit') ?>" class="btn btn-outline btn-sm">
      <?= icon('edit', 15) ?> Edit
    </a>
  </div>
</div>

<div class="grid grid-4 mb-3">
  <div class="stat stat-accent">
    <p class="stat-label">Registered</p>
    <p class="stat-value"><?= count($participants) ?></p>
    <p class="stat-sub">of <?= (int) $event['capacity'] ?> capacity</p>
  </div>
  <div class="stat">
    <p class="stat-label">Spots left</p>
    <p class="stat-value"><?= $spotsLeft ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Expected</p>
    <p class="stat-value" style="font-size:1.25rem"><?= money($expected) ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Collected</p>
    <p class="stat-value" style="font-size:1.25rem;color:var(--ok)"><?= money($collected) ?></p>
    <?php if ($expected > $collected): ?>
      <p class="stat-sub"><?= money($expected - $collected) ?> outstanding</p>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-2 mb-3">
  <div class="card">
    <h2 class="mb-2">Details</h2>
    <div class="summary">
      <div class="summary-row"><span class="muted">Starts</span><strong><?= e(fdate($event['start_datetime'], 'D, M j · g:i A')) ?></strong></div>
      <div class="summary-row"><span class="muted">Ends</span><strong><?= e(fdate($event['end_datetime'], 'D, M j · g:i A')) ?></strong></div>
      <div class="summary-row"><span class="muted">Location</span><strong><?= e($event['location']) ?></strong></div>
      <div class="summary-row"><span class="muted">Court</span><strong><?= e($event['court_name'] ?? 'Not court-specific') ?></strong></div>
      <div class="summary-row"><span class="muted">Skill level</span><strong><?= e($event['level_name'] ?? 'All levels') ?></strong></div>
      <div class="summary-row summary-total"><span>Fee</span><span><?= $event['fee'] > 0 ? money($event['fee']) : 'Free' ?></span></div>
    </div>

    <?php if ($event['description']): ?>
      <p class="label mt-2">Description</p>
      <p class="small muted" style="margin:0;white-space:pre-line"><?= e($event['description']) ?></p>
    <?php endif; ?>

    <?php if ($event['status'] !== 'cancelled'): ?>
      <form method="post" action="<?= url('/admin/events/' . (int) $event['id'] . '/cancel') ?>" class="mt-2 no-print">
        <?= csrf_field() ?>
        <div class="row" style="gap:8px;align-items:flex-end">
          <div class="field grow" style="margin:0">
            <label for="reason">Cancellation reason</label>
            <input type="text" id="reason" name="reason" class="input" maxlength="255" required>
          </div>
          <button type="submit" class="btn btn-danger btn-sm"
                  data-confirm="Cancel this event and notify all participants?">Cancel event</button>
        </div>
      </form>
    <?php endif; ?>
  </div>

  <!-- Fig. 3.7: Add Player to Event -->
  <div class="card no-print">
    <h2 class="mb-1">Add a player</h2>
    <p class="small muted mb-2">Search by name, contact number, email or player code.</p>

    <?php if ($spotsLeft <= 0): ?>
      <div class="alert alert-warning">
        <?= icon('warning', 18) ?>
        <span>This event is at capacity.</span>
      </div>
    <?php else: ?>
      <form method="post" action="<?= url('/admin/events/' . (int) $event['id'] . '/participants') ?>" data-guard>
        <?= csrf_field() ?>

        <div data-player-search="<?= url('/admin/players/search') ?>" style="position:relative">
          <div class="field">
            <label for="player_search">Find player</label>
            <input type="search" id="player_search" class="input" placeholder="Start typing a name…" autocomplete="off">
            <input type="hidden" name="player_id">
          </div>
          <div data-results hidden
               style="border:1px solid var(--border);border-radius:var(--radius-sm);max-height:240px;overflow:auto;margin-top:-8px"></div>
        </div>

        <div class="field">
          <label for="amount_due">Amount due</label>
          <input type="number" id="amount_due" name="amount_due" class="input" step="0.01" min="0"
                 value="<?= e($event['fee']) ?>">
          <p class="field-help">Defaults to the event fee. Set 0 to waive.</p>
        </div>

        <button type="submit" class="btn btn-primary btn-block">
          <?= icon('plus', 15) ?> Add to event
        </button>
      </form>

      <p class="field-help mt-2">
        New player? <a href="<?= url('/admin/players/walk-in') ?>" style="font-weight:700">Record a walk-in</a> first.
      </p>
    <?php endif; ?>
  </div>
</div>

<!-- Fig. 3.8: participant list with payment state -->
<div class="card card-flush">
  <div class="card-head">
    <h2>Participants</h2>
    <button type="button" class="btn btn-sm btn-outline no-print" onclick="window.print()">
      <?= icon('print', 14) ?> Print list
    </button>
  </div>

  <?php if (!$participants): ?>
    <div class="empty">
      <span class="empty-icon"><?= icon('users', 26) ?></span>
      <h3>No participants yet</h3>
      <p class="small">Add players using the panel above.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Player</th><th>Level</th><th>Registered</th>
            <th class="text-right">Due</th><th class="text-right">Paid</th>
            <th>Payment</th><th class="no-print">Record payment</th><th class="no-print"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($participants as $p): ?>
            <?php $balance = (float) $p['amount_due'] - (float) $p['amount_paid']; ?>
            <tr>
              <td>
                <div class="row" style="gap:8px">
                  <span class="avatar avatar-sm"><?= e(initials($p['first_name'], $p['last_name'])) ?></span>
                  <div>
                    <strong class="small" style="display:block"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></strong>
                    <span class="tiny muted"><?= e($p['player_code']) ?><?= $p['is_walk_in'] ? ' · walk-in' : '' ?></span>
                  </div>
                </div>
              </td>
              <td><span class="badge badge-accent"><?= e($p['level_name'] ?? '—') ?></span></td>
              <td class="small muted nowrap"><?= e(fdate($p['registered_at'], 'M j')) ?></td>
              <td class="text-right"><?= money($p['amount_due']) ?></td>
              <td class="text-right"><?= money($p['amount_paid']) ?></td>
              <td>
                <span class="badge badge-<?= status_badge($p['payment_status']) ?>">
                  <?= e(label($p['payment_status'])) ?>
                </span>
              </td>
              <td class="no-print">
                <?php if ($balance > 0): ?>
                  <form method="post"
                        action="<?= url('/admin/events/' . (int) $event['id'] . '/participants/' . (int) $p['id'] . '/payment') ?>"
                        class="row" style="gap:4px">
                    <?= csrf_field() ?>
                    <input type="number" name="amount" class="input" step="0.01" min="0.01"
                           style="width:84px;padding:5px 8px;font-size:.8rem"
                           value="<?= e(number_format($balance, 2, '.', '')) ?>" required>
                    <select name="payment_method" class="select" style="width:88px;padding:5px 24px 5px 8px;font-size:.8rem">
                      <option value="cash">Cash</option>
                      <option value="gcash">GCash</option>
                      <option value="maya">Maya</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary" style="padding:5px 10px">Save</button>
                  </form>
                <?php else: ?>
                  <span class="tiny muted">Settled</span>
                <?php endif; ?>
              </td>
              <td class="text-right no-print">
                <form method="post"
                      action="<?= url('/admin/events/' . (int) $event['id'] . '/participants/' . (int) $p['id'] . '/remove') ?>">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-ghost" aria-label="Remove"
                          data-confirm="Remove this participant from the event?">
                    <?= icon('x', 14) ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
