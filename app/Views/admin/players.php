<?php $pages = (int) ceil($total / $perPage); ?>

<div class="row-between mb-2">
  <div>
    <h1 style="margin:0">Player records</h1>
    <p class="small muted" style="margin:0"><?= number_format($total) ?> <?= pluralise($total, 'player') ?> on file</p>
  </div>
  <a href="<?= url('/admin/players/walk-in') ?>" class="btn btn-primary btn-sm no-print">
    <?= icon('plus', 15) ?> Walk-in registration
  </a>
</div>

<form method="get" action="<?= url('/admin/players') ?>" class="filters no-print" data-auto-filter>
  <div class="field">
    <label for="q">Search</label>
    <input type="search" id="q" name="q" class="input" value="<?= e($filters['q']) ?>"
           placeholder="Name, phone, email or code">
  </div>

  <div class="field">
    <label for="skill_level_id">Skill level</label>
    <select id="skill_level_id" name="skill_level_id" class="select">
      <option value="">All levels</option>
      <?php foreach ($levels as $level): ?>
        <option value="<?= (int) $level['id'] ?>" <?= $filters['skill_level_id'] === (string) $level['id'] ? 'selected' : '' ?>>
          <?= e($level['level_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="field">
    <label for="is_walk_in">Registration</label>
    <select id="is_walk_in" name="is_walk_in" class="select">
      <option value="">All players</option>
      <option value="0" <?= $filters['is_walk_in'] === '0' ? 'selected' : '' ?>>Online account</option>
      <option value="1" <?= $filters['is_walk_in'] === '1' ? 'selected' : '' ?>>Walk-in only</option>
    </select>
  </div>

  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status" class="select">
      <option value="">All</option>
      <option value="active"      <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="inactive"    <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      <option value="blacklisted" <?= $filters['status'] === 'blacklisted' ? 'selected' : '' ?>>Blacklisted</option>
    </select>
  </div>

  <div class="field" style="flex:0 0 auto">
    <button type="submit" class="btn btn-outline"><?= icon('filter', 15) ?> Filter</button>
  </div>
</form>

<div class="card card-flush">
  <?php if (!$players): ?>
    <div class="empty">
      <span class="empty-icon"><?= icon('users', 26) ?></span>
      <h3>No players found</h3>
      <p class="small">Try a different search, or register a walk-in.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Player</th><th>Code</th><th>Contact</th><th>Level</th>
            <th>Type</th><th>Status</th><th>Registered</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($players as $player): ?>
            <tr>
              <td>
                <div class="row" style="gap:9px">
                  <?php if ($player['avatar_path']): ?>
                    <img src="<?= e(uploaded($player['avatar_path'])) ?>" alt="" class="avatar avatar-sm">
                  <?php else: ?>
                    <span class="avatar avatar-sm"><?= e(initials($player['first_name'], $player['last_name'])) ?></span>
                  <?php endif; ?>
                  <strong class="small"><?= e($player['first_name'] . ' ' . $player['last_name']) ?></strong>
                </div>
              </td>
              <td><code class="tiny"><?= e($player['player_code']) ?></code></td>
              <td class="small">
                <?= e($player['phone_number'] ?? '—') ?>
                <?php if ($player['account_email']): ?>
                  <span class="tiny muted" style="display:block"><?= e($player['account_email']) ?></span>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-accent"><?= e($player['level_name'] ?? '—') ?></span></td>
              <td>
                <span class="badge badge-<?= $player['is_walk_in'] ? 'muted' : 'info' ?>">
                  <?= $player['is_walk_in'] ? 'Walk-in' : 'Online' ?>
                </span>
              </td>
              <td><span class="badge badge-<?= status_badge($player['status']) ?>"><?= e(label($player['status'])) ?></span></td>
              <td class="small muted nowrap"><?= e(fdate($player['created_at'])) ?></td>
              <td class="text-right">
                <a href="<?= url('/admin/players/' . (int) $player['id']) ?>" class="btn btn-sm btn-ghost" aria-label="Open">
                  <?= icon('chevron-right', 15) ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($pages > 1): ?>
  <?php
  $query = $filters;
  $build = static function (int $p) use ($query): string {
      $query['page'] = $p;
      return url('/admin/players?' . http_build_query($query));
  };
  ?>
  <nav class="pagination no-print" aria-label="Pagination">
    <a href="<?= $build(max(1, $page - 1)) ?>" class="<?= $page <= 1 ? 'disabled' : '' ?>">Prev</a>
    <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
      <a href="<?= $build($p) ?>" class="<?= $p === $page ? 'current' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
    <a href="<?= $build(min($pages, $page + 1)) ?>" class="<?= $page >= $pages ? 'disabled' : '' ?>">Next</a>
  </nav>
<?php endif; ?>
