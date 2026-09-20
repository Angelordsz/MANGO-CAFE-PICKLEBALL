<div class="row-between mb-2">
  <div>
    <h1 style="margin:0">Send notifications</h1>
    <p class="small muted" style="margin:0">Announcements delivered inside the app.</p>
  </div>
</div>

<div class="grid grid-3 mb-2">
  <div class="stat stat-accent">
    <p class="stat-label">Total sent</p>
    <p class="stat-value"><?= (int) $stats['total'] ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Unread</p>
    <p class="stat-value"><?= (int) $stats['unread'] ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Today</p>
    <p class="stat-value"><?= (int) $stats['today'] ?></p>
  </div>
</div>

<div class="alert alert-info mb-3">
  <?= icon('info', 18) ?>
  <span>
    Notifications appear in the recipient's in-app inbox. Email and SMS gateways are outside
    the approved project scope, so walk-in players without an account cannot be reached here.
  </span>
</div>

<div class="grid grid-2">
  <div class="card">
    <h2 class="mb-2">Compose</h2>

    <form method="post" action="<?= url('/admin/notifications') ?>" data-guard>
      <?= csrf_field() ?>

      <div class="field">
        <label for="audience">Send to</label>
        <select id="audience" name="audience" class="select" required
                onchange="document.getElementById('single-box').hidden = this.value !== 'single'">
          <option value="all">Everyone with an account</option>
          <option value="customer">All customers</option>
          <option value="staff">Staff only</option>
          <option value="admin">Administrators only</option>
          <option value="single">One specific player</option>
        </select>
      </div>

      <div id="single-box" hidden>
        <div data-player-search="<?= url('/admin/players/search') ?>" style="position:relative">
          <div class="field">
            <label for="player_search">Find player</label>
            <input type="search" id="player_search" class="input" placeholder="Start typing a name…" autocomplete="off">
            <input type="hidden" name="player_id">
          </div>
          <div data-results hidden
               style="border:1px solid var(--border);border-radius:var(--radius-sm);max-height:220px;overflow:auto;margin-top:-8px"></div>
        </div>
      </div>

      <div class="field">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" class="input <?= has_error('title') ? 'is-invalid' : '' ?>"
               value="<?= old('title') ?>" required maxlength="150" placeholder="Courts closed Sunday">
        <?= error_for('title') ?>
      </div>

      <div class="field">
        <label for="message">Message</label>
        <textarea id="message" name="message" class="textarea <?= has_error('message') ? 'is-invalid' : '' ?>"
                  rows="4" required maxlength="2000"><?= old('message') ?></textarea>
        <?= error_for('message') ?>
      </div>

      <div class="field">
        <label for="link">Link <span class="label-hint">(optional, e.g. /events)</span></label>
        <input type="text" id="link" name="link" class="input" maxlength="255" value="<?= old('link') ?>">
      </div>

      <button type="submit" class="btn btn-primary btn-block" data-loading="Sending…">
        <?= icon('bell', 15) ?> Send notification
      </button>
    </form>
  </div>

  <div class="card card-flush">
    <div class="card-head"><h2>Recently sent</h2></div>
    <div class="card-body" style="padding-top:0;max-height:520px;overflow:auto">
      <?php if (!$sent): ?>
        <p class="small muted mt-2">Nothing sent yet.</p>
      <?php else: ?>
        <?php foreach ($sent as $note): ?>
          <div class="list-item">
            <span class="thumb" style="width:34px;height:34px"><?= icon('bell', 15) ?></span>
            <div class="grow">
              <strong class="small" style="display:block"><?= e($note['title']) ?></strong>
              <p class="tiny muted" style="margin:0"><?= e(mb_strimwidth($note['message'], 0, 90, '…')) ?></p>
              <span class="tiny muted">
                to <?= e($note['recipient'] ?? 'player') ?>
                · <?= e(fdate($note['created_at'], 'M j, g:i A')) ?>
              </span>
            </div>
            <span class="badge badge-<?= $note['status'] === 'unread' ? 'warn' : 'muted' ?>">
              <?= e(label($note['status'])) ?>
            </span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
