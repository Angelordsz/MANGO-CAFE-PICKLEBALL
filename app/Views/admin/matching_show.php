<?php
$perMatch = $pool['match_format'] === 'singles' ? 2 : 4;
$active   = array_filter($signups, static fn($s) => $s['status'] !== 'cancelled');
$canRun   = count($active) >= $perMatch;
$leftover = count($active) % $perMatch;
?>

<div class="row mb-2" style="gap:10px">
  <a href="<?= url('/admin/matching') ?>" class="icon-btn no-print"
     style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div class="grow">
    <h1 style="margin:0"><?= e($pool['level_name']) ?> · <?= e(label($pool['match_format'])) ?></h1>
    <p class="small muted" style="margin:0">
      <?= e($pool['pool_code']) ?> ·
      <?= e(fdate($pool['match_date'], 'l, M j')) ?> ·
      <?= e(ftimerange($pool['start_time'], $pool['end_time'])) ?>
    </p>
  </div>
  <span class="badge badge-<?= status_badge($pool['status']) ?>"><?= e(label($pool['status'])) ?></span>
</div>

<!-- A12 control panel -->
<div class="card card-lg mb-3 no-print" style="<?= $canRun && $pool['status'] !== 'generated' ? 'border-color:var(--accent)' : '' ?>">
  <div class="row-between mb-2">
    <div>
      <h2 style="margin:0">Generate match assignments</h2>
      <p class="small muted" style="margin:0">
        <?= count($active) ?> registered · <?= intdiv(count($active), $perMatch) ?>
        <?= pluralise(intdiv(count($active), $perMatch), 'match', 'matches') ?> possible
        <?php if ($leftover > 0): ?>
          · <strong><?= $leftover ?> <?= pluralise($leftover, 'player') ?> will be left over</strong>
        <?php endif; ?>
      </p>
    </div>
  </div>

  <?php if (!$canRun): ?>
    <div class="alert alert-warning">
      <?= icon('warning', 18) ?>
      <span>
        Need at least <?= $perMatch ?> players for <?= e($pool['match_format']) ?>.
        Currently <?= count($active) ?> registered.
      </span>
    </div>
  <?php else: ?>
    <div class="btn-group">
      <?php if ($pool['status'] === 'open'): ?>
        <form method="post" action="<?= url('/admin/matching/' . (int) $pool['id'] . '/lock') ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline"
                  data-confirm="Close registration for this session?">
            <?= icon('clock', 15) ?> Close registration
          </button>
        </form>
      <?php endif; ?>

      <?php if (in_array($pool['status'], ['open','locked'], true)): ?>
        <form method="post" action="<?= url('/admin/matching/' . (int) $pool['id'] . '/generate') ?>" data-guard>
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary" data-loading="Drawing…"
                  data-confirm="Draw random match-ups now? Existing assignments for this session are replaced and everyone is notified.">
            <?= icon('shuffle', 16) ?> Generate match-ups
          </button>
        </form>
      <?php elseif ($pool['status'] === 'generated'): ?>
        <form method="post" action="<?= url('/admin/matching/' . (int) $pool['id'] . '/generate') ?>" data-guard>
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline"
                  data-confirm="Re-draw match-ups? The current assignments are deleted and replaced.">
            <?= icon('shuffle', 15) ?> Re-draw
          </button>
        </form>
      <?php endif; ?>

      <?php if ($pool['status'] !== 'cancelled'): ?>
        <form method="post" action="<?= url('/admin/matching/' . (int) $pool['id'] . '/cancel') ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-ghost"
                  data-confirm="Cancel this session and notify everyone registered?">
            Cancel session
          </button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div class="grid grid-2 mb-3">
  <!-- Registered players -->
  <div class="card card-flush">
    <div class="card-head">
      <h2>Registered players</h2>
      <span class="badge badge-muted"><?= count($active) ?>/<?= (int) $pool['max_players'] ?></span>
    </div>

    <div class="card-body" style="padding-top:0">
      <?php if (!$active): ?>
        <p class="small muted mt-2">No one has registered yet.</p>
      <?php else: ?>
        <?php foreach ($signups as $signup): ?>
          <?php if ($signup['status'] === 'cancelled') { continue; } ?>
          <div class="list-item">
            <?php if ($signup['avatar_path']): ?>
              <img src="<?= e(uploaded($signup['avatar_path'])) ?>" alt="" class="avatar avatar-sm">
            <?php else: ?>
              <span class="avatar avatar-sm"><?= e(initials($signup['first_name'], $signup['last_name'])) ?></span>
            <?php endif; ?>

            <div class="grow">
              <strong class="small" style="display:block">
                <?= e($signup['first_name'] . ' ' . $signup['last_name']) ?>
              </strong>
              <span class="tiny muted">
                <?= e($signup['player_code']) ?>
                <?= $signup['is_walk_in'] ? ' · walk-in' : '' ?>
              </span>
            </div>

            <span class="badge badge-<?= status_badge($signup['status']) ?>"><?= e(label($signup['status'])) ?></span>

            <?php if ($pool['status'] === 'open'): ?>
              <form method="post" action="<?= url('/admin/matching/' . (int) $pool['id'] . '/remove') ?>" class="no-print">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= (int) $signup['id'] ?>">
                <button type="submit" class="btn btn-sm btn-ghost" aria-label="Remove"
                        data-confirm="Remove this player from the session?">
                  <?= icon('x', 14) ?>
                </button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Add a player -->
  <div class="card no-print">
    <h2 class="mb-1">Add a player</h2>
    <p class="small muted mb-2">
      For walk-ins who signed up at the counter. Search by name, phone, email or player code.
    </p>

    <?php if ($pool['status'] !== 'open'): ?>
      <div class="alert alert-info">
        <?= icon('info', 18) ?>
        <span>Registration is closed for this session.</span>
      </div>
    <?php else: ?>
      <form method="post" action="<?= url('/admin/matching/' . (int) $pool['id'] . '/add') ?>" data-guard>
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

        <button type="submit" class="btn btn-primary btn-block mt-2">
          <?= icon('plus', 15) ?> Add to session
        </button>
      </form>

      <p class="field-help mt-2">
        Not registered yet?
        <a href="<?= url('/admin/players/walk-in') ?>" style="font-weight:700">Record a walk-in player</a>.
      </p>
    <?php endif; ?>
  </div>
</div>

<!-- Generated match-ups -->
<div class="card card-flush">
  <div class="card-head">
    <h2>Match assignments</h2>
    <?php if ($assignments): ?>
      <button type="button" class="btn btn-sm btn-outline no-print" onclick="window.print()">
        <?= icon('print', 14) ?> Print
      </button>
    <?php endif; ?>
  </div>

  <div class="card-body">
    <?php if (!$assignments): ?>
      <div class="empty" style="padding:26px">
        <span class="empty-icon"><?= icon('shuffle', 22) ?></span>
        <h3>Not generated yet</h3>
        <p class="small" style="margin:0">Match-ups appear here once you draw them.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-2">
        <?php foreach ($assignments as $match): ?>
          <?php
            $team1 = array_filter($match['players'], static fn($p) => (int) $p['team'] === 1);
            $team2 = array_filter($match['players'], static fn($p) => (int) $p['team'] === 2);
          ?>
          <?php $result = $results[(int) $match['id']] ?? null; ?>
          <div class="card" style="box-shadow:none">
            <div class="row-between mb-1">
              <span class="badge badge-info">Match <?= (int) $match['match_no'] ?></span>
              <span class="tiny muted">
                <?= e($match['court_name'] ?? 'Court TBA') ?> · <?= e(ftime($match['start_time'])) ?>
              </span>
            </div>

            <div class="summary">
              <div class="summary-row">
                <span class="muted <?= $result && (int) $result['winning_team'] === 1 ? 'strong' : '' ?>">
                  Team 1
                  <?php if ($result && (int) $result['winning_team'] === 1): ?>
                    <span class="badge badge-ok tiny">W</span>
                  <?php endif; ?>
                </span>
                <span class="text-right">
                  <?php foreach ($team1 as $p): ?>
                    <span class="pill tiny" style="margin-left:4px"><?= e($p['first_name'] . ' ' . mb_substr($p['last_name'], 0, 1) . '.') ?></span>
                  <?php endforeach; ?>
                  <?php if ($result): ?>
                    <strong style="margin-left:6px"><?= (int) $result['team1_score'] ?></strong>
                  <?php endif; ?>
                </span>
              </div>
              <div class="text-center tiny muted">vs</div>
              <div class="summary-row">
                <span class="muted <?= $result && (int) $result['winning_team'] === 2 ? 'strong' : '' ?>">
                  Team 2
                  <?php if ($result && (int) $result['winning_team'] === 2): ?>
                    <span class="badge badge-ok tiny">W</span>
                  <?php endif; ?>
                </span>
                <span class="text-right">
                  <?php foreach ($team2 as $p): ?>
                    <span class="pill tiny" style="margin-left:4px"><?= e($p['first_name'] . ' ' . mb_substr($p['last_name'], 0, 1) . '.') ?></span>
                  <?php endforeach; ?>
                  <?php if ($result): ?>
                    <strong style="margin-left:6px"><?= (int) $result['team2_score'] ?></strong>
                  <?php endif; ?>
                </span>
              </div>
            </div>

            <?php if ($canScore): ?>
              <?php // ENHANCEMENT -- outside the approved scope. See docs/ENHANCEMENTS.md. ?>
              <form method="post"
                    action="<?= url('/admin/matching/' . (int) $pool['id'] . '/matches/' . (int) $match['id'] . '/result') ?>"
                    class="row no-print mt-1" style="gap:6px;align-items:center">
                <?= csrf_field() ?>
                <span class="tiny muted" style="flex:0 0 auto">Score</span>
                <input type="number" name="team1_score" class="input" min="0" max="99" required
                       style="width:58px;padding:5px 7px;font-size:.8rem"
                       value="<?= $result ? (int) $result['team1_score'] : '' ?>" aria-label="Team 1 score">
                <span class="tiny muted">–</span>
                <input type="number" name="team2_score" class="input" min="0" max="99" required
                       style="width:58px;padding:5px 7px;font-size:.8rem"
                       value="<?= $result ? (int) $result['team2_score'] : '' ?>" aria-label="Team 2 score">
                <button type="submit" class="btn btn-sm btn-outline" style="padding:5px 11px">
                  <?= $result ? 'Update' : 'Save' ?>
                </button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
