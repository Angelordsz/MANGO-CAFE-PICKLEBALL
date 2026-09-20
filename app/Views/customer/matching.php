<div class="row-between mb-2">
  <div>
    <h1 style="margin:0">Player matching</h1>
    <p class="small muted" style="margin:0">Came without a partner? Join a session and we will pair you up.</p>
  </div>
  <a href="<?= url('/matching/results') ?>" class="btn btn-outline btn-sm">My results</a>
</div>

<div class="alert alert-info mb-3">
  <?= icon('info', 18) ?>
  <span>
    Pick a session that matches your <strong>date</strong> and <strong>skill level</strong>.
    When registration closes, the café randomly pairs everyone in that session into partners
    and opponents, then assigns a court.
  </span>
</div>

<?php if ($signups): ?>
  <div class="section-head"><h2>You are registered for</h2></div>

  <div class="grid grid-2 mb-3">
    <?php foreach ($signups as $signup): ?>
      <div class="card">
        <div class="row-between mb-1">
          <span class="badge badge-<?= status_badge($signup['status']) ?>"><?= e(label($signup['status'])) ?></span>
          <span class="tiny muted"><?= e($signup['pool_code']) ?></span>
        </div>

        <h3 style="margin:0 0 .25rem"><?= e($signup['level_name']) ?> · <?= e(label($signup['match_format'])) ?></h3>
        <p class="small muted mb-2">
          <?= icon('calendar', 13) ?> <?= e(human_date($signup['match_date'])) ?>
          · <?= icon('clock', 13) ?> <?= e(ftimerange($signup['start_time'], $signup['end_time'])) ?>
        </p>

        <?php if ($signup['status'] === 'matched'): ?>
          <a href="<?= url('/matching/results') ?>" class="btn btn-sm btn-primary btn-block">View your match</a>
        <?php elseif ($signup['pool_status'] === 'open'): ?>
          <form method="post" action="<?= url('/matching/' . (int) $signup['id'] . '/cancel') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-ghost btn-block"
                    data-confirm="Cancel your registration for this session?">
              Cancel registration
            </button>
          </form>
        <?php else: ?>
          <p class="tiny muted" style="margin:0">
            Registration is closed. Match-ups will be posted soon.
          </p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="section-head">
  <h2>Open sessions</h2>
  <span class="small muted">Your level: <strong><?= e($player['level_name'] ?? 'Unrated') ?></strong></span>
</div>

<?php if (!$pools): ?>
  <div class="card">
    <div class="empty">
      <span class="empty-icon"><?= icon('shuffle', 26) ?></span>
      <h3>No open sessions right now</h3>
      <p class="small">
        The café opens matching sessions a few days ahead. Check back, or reserve a court
        and bring your own group.
      </p>
      <a href="<?= url('/reserve/court') ?>" class="btn btn-outline mt-1">Reserve a court</a>
    </div>
  </div>
<?php else: ?>
  <div class="grid grid-2">
    <?php foreach ($pools as $pool): ?>
      <?php
        $full       = (int) $pool['signups'] >= (int) $pool['max_players'];
        $joined     = (int) $pool['joined'] > 0;
        $sameLevel  = (int) $pool['skill_level_id'] === (int) ($player['skill_level_id'] ?? 0);
        $percent    = $pool['max_players'] > 0
                    ? min(100, round(100 * $pool['signups'] / $pool['max_players']))
                    : 0;
      ?>
      <div class="card <?= $sameLevel ? 'stat-accent' : '' ?>">
        <div class="row-between mb-1">
          <span class="badge badge-accent"><?= e($pool['level_name']) ?></span>
          <?php if ($sameLevel): ?>
            <span class="badge badge-ok">Your level</span>
          <?php endif; ?>
        </div>

        <h3 style="margin:0 0 .25rem">
          <?= e(label($pool['time_block'])) ?> <?= e(label($pool['match_format'])) ?>
        </h3>
        <p class="small muted mb-2">
          <?= icon('calendar', 13) ?> <?= e(human_date($pool['match_date'])) ?>
          · <?= icon('clock', 13) ?> <?= e(ftimerange($pool['start_time'], $pool['end_time'])) ?>
        </p>

        <div class="mb-2">
          <div class="row-between tiny muted mb-1">
            <span><?= (int) $pool['signups'] ?> of <?= (int) $pool['max_players'] ?> registered</span>
            <span>min <?= (int) $pool['min_players'] ?> to run</span>
          </div>
          <div class="step-bar" style="background:var(--border)">
            <div style="height:100%;width:<?= $percent ?>%;background:var(--accent);border-radius:2px"></div>
          </div>
        </div>

        <?php if ($joined): ?>
          <button type="button" class="btn btn-sm btn-outline btn-block" disabled>Already registered</button>
        <?php elseif ($full): ?>
          <button type="button" class="btn btn-sm btn-outline btn-block" disabled>Session full</button>
        <?php else: ?>
          <form method="post" action="<?= url('/matching/register') ?>" data-guard>
            <?= csrf_field() ?>
            <input type="hidden" name="pool_id" value="<?= (int) $pool['id'] ?>">
            <button type="submit" class="btn btn-sm btn-primary btn-block" data-loading="Joining…">
              Join this session
            </button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
