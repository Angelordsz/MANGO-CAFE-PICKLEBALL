<div class="row mb-2" style="gap:10px">
  <a href="<?= url('/matching') ?>" class="icon-btn" style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div>
    <h1 style="margin:0">My match results</h1>
    <p class="small muted" style="margin:0">Your generated match-ups, newest first.</p>
  </div>
</div>

<?php if (!$matches): ?>
  <div class="card">
    <div class="empty">
      <span class="empty-icon"><?= icon('shuffle', 26) ?></span>
      <h3>No matches yet</h3>
      <p class="small mb-2">
        Once you join a matching session and the café generates the pairings,
        your match-ups appear here.
      </p>
      <a href="<?= url('/matching') ?>" class="btn btn-primary">Find a session</a>
    </div>
  </div>
<?php else: ?>
  <div class="grid grid-2">
    <?php foreach ($matches as $match): ?>
      <?php
        $team1 = array_filter($match['players'], static fn($p) => (int) $p['team'] === 1);
        $team2 = array_filter($match['players'], static fn($p) => (int) $p['team'] === 2);
        $myTeam = (int) $match['my_team'];
      ?>
      <article class="card">
        <div class="row-between mb-2">
          <div>
            <span class="badge badge-info">Match <?= (int) $match['match_no'] ?></span>
            <?php if ((int) $match['round_no'] > 1): ?>
              <span class="badge badge-muted">Round <?= (int) $match['round_no'] ?></span>
            <?php endif; ?>
          </div>
          <span class="tiny muted"><?= e($match['pool_code']) ?></span>
        </div>

        <p class="small muted mb-2">
          <?= icon('calendar', 13) ?> <?= e(fdate($match['match_date'], 'D, M j')) ?>
          · <?= icon('clock', 13) ?> <?= e(ftime($match['start_time'])) ?>
          · <?= icon('court', 13) ?> <?= e($match['court_name'] ?? 'Court TBA') ?>
        </p>

        <div class="summary">
          <?php foreach ([1 => $team1, 2 => $team2] as $teamNo => $members): ?>
            <div class="summary-row" style="align-items:flex-start">
              <span class="<?= $teamNo === $myTeam ? 'strong' : 'muted' ?>" style="min-width:64px">
                Team <?= $teamNo ?>
                <?php if ($teamNo === $myTeam): ?>
                  <span class="badge badge-accent tiny" style="display:block;margin-top:3px">You</span>
                <?php endif; ?>
              </span>
              <span class="text-right">
                <?php foreach ($members as $member): ?>
                  <span class="pill tiny" style="margin:2px 0 2px 4px">
                    <?= e($member['first_name'] . ' ' . mb_substr($member['last_name'], 0, 1) . '.') ?>
                  </span>
                <?php endforeach; ?>
              </span>
            </div>
            <?php if ($teamNo === 1): ?>
              <div class="text-center tiny muted" style="margin:2px 0">vs</div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>

        <p class="tiny muted mt-1" style="margin-bottom:0">
          <?= e(label($match['match_format'])) ?> · <?= e($match['level_name'] ?? '') ?>
          · <span class="badge badge-<?= status_badge($match['status']) ?>"><?= e(label($match['status'])) ?></span>
        </p>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
