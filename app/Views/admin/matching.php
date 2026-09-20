<div class="row-between mb-2">
  <div>
    <h1 style="margin:0">Player matching</h1>
    <p class="small muted" style="margin:0">
      Create sessions by date, time block and skill level, then generate random match-ups.
    </p>
  </div>
  <a href="<?= url('/admin/matching/create') ?>" class="btn btn-primary btn-sm no-print">
    <?= icon('plus', 15) ?> New session
  </a>
</div>

<div class="alert alert-info mb-2">
  <?= icon('info', 18) ?>
  <span>
    Match-ups are drawn at random from everyone registered in a session. Fairness comes from
    the session itself — all its players declared the same skill level.
  </span>
</div>

<form method="get" action="<?= url('/admin/matching') ?>" class="filters no-print" data-auto-filter>
  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status" class="select">
      <option value="">All statuses</option>
      <?php foreach (['open','locked','generated','completed','cancelled'] as $status): ?>
        <option value="<?= $status ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
          <?= e(label($status)) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="date">Date</label>
    <input type="date" id="date" name="date" class="input" value="<?= e($filters['date']) ?>">
  </div>
  <div class="field" style="flex:0 0 auto">
    <button type="submit" class="btn btn-outline"><?= icon('filter', 15) ?> Filter</button>
  </div>
</form>

<?php if (!$pools): ?>
  <div class="card">
    <div class="empty">
      <span class="empty-icon"><?= icon('shuffle', 26) ?></span>
      <h3>No matching sessions</h3>
      <p class="small mb-2">Create one so players without a partner can register.</p>
      <a href="<?= url('/admin/matching/create') ?>" class="btn btn-primary">Create a session</a>
    </div>
  </div>
<?php else: ?>
  <div class="grid grid-2">
    <?php foreach ($pools as $pool): ?>
      <?php
        $perMatch = $pool['match_format'] === 'singles' ? 2 : 4;
        $canRun   = (int) $pool['signups'] >= $perMatch;
        $percent  = $pool['max_players'] > 0
                  ? min(100, round(100 * $pool['signups'] / $pool['max_players']))
                  : 0;
      ?>
      <div class="card">
        <div class="row-between mb-1">
          <span class="badge badge-<?= status_badge($pool['status']) ?>"><?= e(label($pool['status'])) ?></span>
          <span class="tiny muted"><?= e($pool['pool_code']) ?></span>
        </div>

        <h3 style="margin:0 0 .25rem">
          <?= e($pool['level_name']) ?> · <?= e(label($pool['match_format'])) ?>
        </h3>
        <p class="small muted mb-2">
          <?= icon('calendar', 13) ?> <?= e(human_date($pool['match_date'])) ?>
          · <?= icon('clock', 13) ?> <?= e(ftimerange($pool['start_time'], $pool['end_time'])) ?>
        </p>

        <div class="mb-2">
          <div class="row-between tiny muted mb-1">
            <span><?= (int) $pool['signups'] ?> of <?= (int) $pool['max_players'] ?> registered</span>
            <span><?= (int) $pool['matches'] ?> <?= pluralise((int) $pool['matches'], 'match', 'matches') ?> generated</span>
          </div>
          <div class="step-bar" style="background:var(--border)">
            <div style="height:100%;width:<?= $percent ?>%;background:var(--accent);border-radius:2px"></div>
          </div>
        </div>

        <div class="btn-group">
          <a href="<?= url('/admin/matching/' . (int) $pool['id']) ?>" class="btn btn-sm btn-outline grow">
            Manage
          </a>
          <?php if (in_array($pool['status'], ['open','locked'], true) && $canRun): ?>
            <form method="post" action="<?= url('/admin/matching/' . (int) $pool['id'] . '/generate') ?>">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-primary"
                      data-confirm="Generate random match-ups now? Any existing assignments for this session are replaced.">
                <?= icon('shuffle', 14) ?> Generate
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
