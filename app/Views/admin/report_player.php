<?php
use App\Core\View;

echo View::partial('admin.partials.report_head', [
    'reportTitle' => 'Player activity report',
    'reportPath'  => '/admin/reports/player',
    'from'        => $from,
    'to'          => $to,
]);

$walkIns = 0;
foreach ($rows as $row) {
    if ($row['is_walk_in']) {
        $walkIns++;
    }
}
?>

<div class="grid grid-4 mb-3">
  <div class="stat stat-accent">
    <p class="stat-label">Active players</p>
    <p class="stat-value"><?= count($rows) ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Walk-in records</p>
    <p class="stat-value"><?= $walkIns ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Reservations</p>
    <p class="stat-value"><?= array_sum(array_map(static fn($r) => (int) $r['reservations'], $rows)) ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Matches played</p>
    <p class="stat-value"><?= array_sum(array_map(static fn($r) => (int) $r['matches'], $rows)) ?></p>
  </div>
</div>

<div class="card card-flush">
  <?php if (!$rows): ?>
    <div class="empty">
      <span class="empty-icon"><?= icon('users', 26) ?></span>
      <h3>No player activity</h3>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Code</th><th>Player</th><th>Contact</th><th>Level</th><th>Type</th>
            <th class="text-right">Reservations</th><th class="text-right">Sign-ups</th>
            <th class="text-right">Matches</th><th class="text-right">Paid</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><code class="tiny"><?= e($row['player_code']) ?></code></td>
              <td><strong class="small"><?= e($row['first_name'] . ' ' . $row['last_name']) ?></strong></td>
              <td class="small muted"><?= e($row['phone_number'] ?? '—') ?></td>
              <td><span class="badge badge-accent"><?= e($row['level_name'] ?? '—') ?></span></td>
              <td>
                <span class="badge badge-<?= $row['is_walk_in'] ? 'muted' : 'info' ?>">
                  <?= $row['is_walk_in'] ? 'Walk-in' : 'Online' ?>
                </span>
              </td>
              <td class="text-right"><?= (int) $row['reservations'] ?></td>
              <td class="text-right"><?= (int) $row['matching_signups'] ?></td>
              <td class="text-right strong"><?= (int) $row['matches'] ?></td>
              <td class="text-right nowrap"><?= money($row['total_paid']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
