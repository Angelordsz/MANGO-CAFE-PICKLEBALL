<div class="row-between mb-2">
  <div>
    <h1 style="margin:0">Notifications</h1>
    <p class="small muted" style="margin:0">
      <?= $unread ? $unread . ' unread' : 'All caught up' ?>
    </p>
  </div>
  <?php if ($unread): ?>
    <form method="post" action="<?= url('/notifications/read-all') ?>">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-outline btn-sm">Mark all read</button>
    </form>
  <?php endif; ?>
</div>

<?php if (!$notifications): ?>
  <div class="card">
    <div class="empty">
      <span class="empty-icon"><?= icon('bell', 26) ?></span>
      <h3>Nothing yet</h3>
      <p class="small">Reservation updates and match assignments will show up here.</p>
    </div>
  </div>
<?php else: ?>
  <div class="card card-flush">
    <div class="card-body">
      <?php foreach ($notifications as $note): ?>
        <div class="list-item" style="<?= $note['status'] === 'unread' ? 'background:var(--bg-sunken);margin:0 -16px;padding-left:16px;padding-right:16px' : '' ?>">
          <span class="thumb" style="width:38px;height:38px">
            <?= icon(match (true) {
                str_contains($note['type'], 'reservation') => 'ticket',
                str_contains($note['type'], 'match')       => 'shuffle',
                str_contains($note['type'], 'payment')     => 'money',
                str_contains($note['type'], 'event')       => 'trophy',
                default                                    => 'bell',
            }, 17) ?>
          </span>

          <div class="grow">
            <strong class="small" style="display:block"><?= e($note['title']) ?></strong>
            <p class="small muted" style="margin:0"><?= e($note['message']) ?></p>
            <span class="tiny muted"><?= e(fdate($note['created_at'], 'M j, g:i A')) ?></span>
          </div>

          <div class="row" style="gap:6px">
            <?php if ($note['link']): ?>
              <a href="<?= url($note['link']) ?>" class="btn btn-sm btn-ghost" aria-label="Open">
                <?= icon('chevron-right', 15) ?>
              </a>
            <?php endif; ?>
            <?php if ($note['status'] === 'unread'): ?>
              <form method="post" action="<?= url('/notifications/' . (int) $note['id'] . '/read') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-ghost" aria-label="Mark as read">
                  <?= icon('check', 15) ?>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
