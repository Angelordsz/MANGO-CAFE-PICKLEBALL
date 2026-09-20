<div class="row-between mb-2">
  <div>
    <h1 style="margin:0">Events</h1>
    <p class="small muted" style="margin:0">Clinics, leagues and social play sessions.</p>
  </div>
  <a href="<?= url('/admin/events/create') ?>" class="btn btn-primary btn-sm no-print">
    <?= icon('plus', 15) ?> Create new event
  </a>
</div>

<form method="get" action="<?= url('/admin/events') ?>" class="filters no-print" data-auto-filter>
  <div class="field">
    <label for="status">Status</label>
    <select id="status" name="status" class="select">
      <option value="">All statuses</option>
      <?php foreach (['draft','upcoming','active','ongoing','completed','cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= e(label($s)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="type">Type</label>
    <select id="type" name="type" class="select">
      <option value="">All types</option>
      <?php foreach (['open_play','clinic','league','exhibition','social','private'] as $t): ?>
        <option value="<?= $t ?>" <?= $filters['type'] === $t ? 'selected' : '' ?>><?= e(label($t)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="from">From</label>
    <input type="date" id="from" name="from" class="input" value="<?= e($filters['from']) ?>">
  </div>
  <div class="field">
    <label for="to">To</label>
    <input type="date" id="to" name="to" class="input" value="<?= e($filters['to']) ?>">
  </div>
  <div class="field" style="flex:0 0 auto">
    <button type="submit" class="btn btn-outline"><?= icon('filter', 15) ?> Filter</button>
  </div>
</form>

<?php if (!$events): ?>
  <div class="card">
    <div class="empty">
      <span class="empty-icon"><?= icon('trophy', 26) ?></span>
      <h3>No events</h3>
      <p class="small mb-2">Create one to start tracking participants and payments.</p>
      <a href="<?= url('/admin/events/create') ?>" class="btn btn-primary">Create new event</a>
    </div>
  </div>
<?php else: ?>
  <div class="card card-flush">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Event</th><th>Type</th><th>When</th><th>Court</th>
            <th class="text-right">Participants</th><th class="text-right">Fee</th><th>Status</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $event): ?>
            <tr>
              <td>
                <strong class="small"><?= e($event['title']) ?></strong>
                <span class="tiny muted" style="display:block"><?= e($event['event_code']) ?></span>
              </td>
              <td><span class="badge badge-accent"><?= e(label($event['event_type'])) ?></span></td>
              <td class="small nowrap"><?= e(fdate($event['start_datetime'], 'M j, g:i A')) ?></td>
              <td class="small muted"><?= e($event['court_name'] ?? '—') ?></td>
              <td class="text-right">
                <?= (int) $event['participant_count'] ?><span class="muted">/<?= (int) $event['capacity'] ?></span>
              </td>
              <td class="text-right"><?= $event['fee'] > 0 ? money($event['fee']) : 'Free' ?></td>
              <td><span class="badge badge-<?= status_badge($event['status']) ?>"><?= e(label($event['status'])) ?></span></td>
              <td class="text-right">
                <a href="<?= url('/admin/events/' . (int) $event['id']) ?>" class="btn btn-sm btn-ghost" aria-label="Open">
                  <?= icon('chevron-right', 15) ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
