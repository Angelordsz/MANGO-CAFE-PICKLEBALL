<?php use App\Core\Auth; ?>

<div class="row mb-2" style="gap:10px">
  <a href="<?= url('/admin/players') ?>" class="icon-btn no-print"
     style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div class="grow">
    <h1 style="margin:0"><?= e($player['first_name'] . ' ' . $player['last_name']) ?></h1>
    <p class="small muted" style="margin:0"><?= e($player['player_code']) ?></p>
  </div>
  <div class="btn-group no-print">
    <a href="<?= url('/admin/players/' . (int) $player['id'] . '/edit') ?>" class="btn btn-outline btn-sm">
      <?= icon('edit', 15) ?> Edit
    </a>
    <a href="<?= url('/admin/reservations/create?player_id=' . (int) $player['id']) ?>" class="btn btn-primary btn-sm">
      <?= icon('plus', 15) ?> Book
    </a>
  </div>
</div>

<div class="grid grid-2 mb-3">
  <div class="card">
    <div class="row mb-2" style="gap:12px">
      <?php if ($player['avatar_path']): ?>
        <img src="<?= e(uploaded($player['avatar_path'])) ?>" alt="" class="avatar avatar-lg">
      <?php else: ?>
        <span class="avatar avatar-lg"><?= e(initials($player['first_name'], $player['last_name'])) ?></span>
      <?php endif; ?>
      <div class="grow">
        <div class="row row-wrap" style="gap:6px">
          <span class="badge badge-accent"><?= e($player['level_name'] ?? 'Unrated') ?></span>
          <span class="badge badge-<?= $player['is_walk_in'] ? 'muted' : 'info' ?>">
            <?= $player['is_walk_in'] ? 'Walk-in' : 'Online account' ?>
          </span>
          <span class="badge badge-<?= status_badge($player['status']) ?>"><?= e(label($player['status'])) ?></span>
        </div>
      </div>
    </div>

    <div class="summary">
      <div class="summary-row"><span class="muted">Phone</span><strong><?= e($player['phone_number'] ?? '—') ?></strong></div>
      <div class="summary-row"><span class="muted">Email</span><strong class="small"><?= e($player['email'] ?? $player['account_email'] ?? '—') ?></strong></div>
      <div class="summary-row"><span class="muted">Gender</span><strong><?= e(label($player['gender'])) ?></strong></div>
      <div class="summary-row"><span class="muted">Age</span><strong><?= $player['age'] ? (int) $player['age'] : '—' ?></strong></div>
      <div class="summary-row"><span class="muted">Preferred time</span><strong><?= e(label($player['preferred_time'])) ?></strong></div>
      <div class="summary-row"><span class="muted">Username</span><strong><?= e($player['username'] ?? 'No account') ?></strong></div>
      <div class="summary-row"><span class="muted">Registered</span><strong><?= e(fdate($player['created_at'], 'M j, Y')) ?></strong></div>
    </div>

    <?php if ($player['notes']): ?>
      <p class="label mt-2">Staff notes</p>
      <p class="small muted" style="margin:0"><?= e($player['notes']) ?></p>
    <?php endif; ?>

    <?php if (Auth::isAdmin()): ?>
      <form method="post" action="<?= url('/admin/players/' . (int) $player['id'] . '/status') ?>" class="mt-2 no-print">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-ghost"
                data-confirm="Change this player's status?">
          <?= $player['status'] === 'active' ? 'Deactivate player' : 'Reactivate player' ?>
        </button>
      </form>
    <?php endif; ?>
  </div>

  <div>
    <div class="grid grid-2 mb-2">
      <div class="stat stat-accent">
        <p class="stat-label">Reservations</p>
        <p class="stat-value"><?= (int) $summary['reservations'] ?></p>
      </div>
      <div class="stat">
        <p class="stat-label">Matches</p>
        <p class="stat-value"><?= (int) $summary['matches_played'] ?></p>
      </div>
      <div class="stat">
        <p class="stat-label">Events</p>
        <p class="stat-value"><?= (int) $summary['events_joined'] ?></p>
      </div>
      <div class="stat">
        <p class="stat-label">Total paid</p>
        <p class="stat-value" style="font-size:1.2rem"><?= money($summary['total_paid']) ?></p>
      </div>
    </div>

    <?php // ENHANCEMENT -- win/loss record. See docs/ENHANCEMENTS.md. ?>
    <?php if (!empty($showStats) && (int) ($stats['games_played'] ?? 0) > 0): ?>
      <div class="card mb-2">
        <div class="row-between mb-1">
          <strong class="small">Match record</strong>
          <span class="badge badge-accent"><?= (int) $stats['win_rate'] ?>% win rate</span>
        </div>
        <div class="row-between small muted mb-1">
          <span><?= (int) $stats['wins'] ?> won · <?= (int) $stats['losses'] ?> lost</span>
          <span><?= (int) $stats['games_played'] ?> played</span>
        </div>
        <div class="step-bar" style="background:var(--bad);overflow:hidden">
          <div style="height:100%;width:<?= (int) $stats['win_rate'] ?>%;background:var(--ok)"></div>
        </div>
      </div>
    <?php endif; ?>

    <div class="card card-flush">
      <div class="card-head"><h2>Recent reservations</h2></div>
      <div class="card-body" style="padding-top:0">
        <?php if (!$reservations): ?>
          <p class="small muted mt-2">No reservations yet.</p>
        <?php else: ?>
          <?php foreach (array_slice($reservations, 0, 6) as $r): ?>
            <div class="list-item">
              <span class="thumb" style="width:34px;height:34px">
                <?= icon($r['reservation_type'] === 'court' ? 'court' : 'hall', 15) ?>
              </span>
              <div class="grow">
                <strong class="small" style="display:block"><?= e($r['facility_name']) ?></strong>
                <span class="tiny muted">
                  <?= e(fdate($r['reservation_date'], 'M j')) ?> · <?= e(ftime($r['start_time'])) ?>
                </span>
              </div>
              <span class="badge badge-<?= status_badge($r['status']) ?>"><?= e(label($r['status'])) ?></span>
              <a href="<?= url('/admin/reservations/' . (int) $r['id']) ?>" class="btn btn-sm btn-ghost no-print">
                <?= icon('chevron-right', 14) ?>
              </a>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="grid grid-2">
  <div class="card card-flush">
    <div class="card-head"><h2>Activity history</h2></div>
    <div class="card-body" style="padding-top:0;max-height:420px;overflow:auto">
      <?php if (!$history): ?>
        <p class="small muted mt-2">No activity recorded.</p>
      <?php else: ?>
        <?php foreach ($history as $entry): ?>
          <div class="list-item">
            <span class="thumb" style="width:32px;height:32px">
              <?= icon(match ($entry['activity_type']) {
                  'reservation'  => 'ticket',
                  'match'        => 'shuffle',
                  'event'        => 'trophy',
                  'payment'      => 'money',
                  default        => 'user',
              }, 14) ?>
            </span>
            <div class="grow">
              <p class="small" style="margin:0"><?= e($entry['description']) ?></p>
              <span class="tiny muted">
                <?= e(fdate($entry['activity_date'])) ?>
                <?= $entry['activity_time'] ? ' · ' . e(ftime($entry['activity_time'])) : '' ?>
              </span>
            </div>
            <?php if ((float) $entry['payment'] > 0): ?>
              <strong class="small"><?= money($entry['payment']) ?></strong>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="card card-flush">
    <div class="card-head"><h2>Match assignments</h2></div>
    <div class="card-body" style="padding-top:0">
      <?php if (!$matches): ?>
        <p class="small muted mt-2">This player has not been matched yet.</p>
      <?php else: ?>
        <?php foreach ($matches as $match): ?>
          <div class="list-item">
            <span class="thumb" style="width:32px;height:32px"><?= icon('shuffle', 14) ?></span>
            <div class="grow">
              <strong class="small" style="display:block">
                Match <?= (int) $match['match_no'] ?> · Team <?= (int) $match['my_team'] ?>
              </strong>
              <span class="tiny muted">
                <?= e(fdate($match['match_date'], 'M j')) ?> ·
                <?= e($match['court_name'] ?? 'Court TBA') ?>
              </span>
            </div>
            <span class="badge badge-<?= status_badge($match['status']) ?>"><?= e(label($match['status'])) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
