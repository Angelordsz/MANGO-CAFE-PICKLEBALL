<?php
use App\Core\Auth;

$spotsLeft = max(0, (int) $event['capacity'] - (int) $event['participant_count']);
$percent   = $event['capacity'] > 0
           ? min(100, round(100 * $event['participant_count'] / $event['capacity']))
           : 0;
?>

<div class="row mb-2" style="gap:10px">
  <a href="<?= url('/events') ?>" class="icon-btn" style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div class="grow">
    <h1 style="margin:0"><?= e($event['title']) ?></h1>
    <p class="small muted" style="margin:0"><?= e($event['event_code']) ?></p>
  </div>
  <span class="badge badge-<?= status_badge($event['status']) ?>"><?= e(label($event['status'])) ?></span>
</div>

<?php if ($event['image_path']): ?>
  <img src="<?= e(uploaded($event['image_path'])) ?>" alt=""
       style="width:100%;height:220px;object-fit:cover;border-radius:var(--radius);margin-bottom:16px">
<?php endif; ?>

<div class="card card-lg mb-2">
  <div class="summary">
    <div class="summary-row">
      <span class="muted"><?= icon('calendar', 14) ?> Starts</span>
      <strong><?= e(fdate($event['start_datetime'], 'l, F j · g:i A')) ?></strong>
    </div>
    <div class="summary-row">
      <span class="muted"><?= icon('clock', 14) ?> Ends</span>
      <strong><?= e(fdate($event['end_datetime'], 'g:i A')) ?></strong>
    </div>
    <div class="summary-row">
      <span class="muted"><?= icon('pin', 14) ?> Location</span>
      <strong><?= e($event['location']) ?><?= $event['court_name'] ? ' · ' . e($event['court_name']) : '' ?></strong>
    </div>
    <div class="summary-row">
      <span class="muted"><?= icon('star', 14) ?> Skill level</span>
      <strong><?= e($event['level_name'] ?? 'Open to all levels') ?></strong>
    </div>
    <div class="summary-row summary-total">
      <span>Entry fee</span>
      <span><?= $event['fee'] > 0 ? money($event['fee']) : 'Free' ?></span>
    </div>
  </div>
</div>

<?php if ($event['description']): ?>
  <div class="card mb-2">
    <p class="label">About this event</p>
    <p class="small" style="margin:0;white-space:pre-line"><?= e($event['description']) ?></p>
  </div>
<?php endif; ?>

<div class="card mb-2">
  <div class="row-between mb-1">
    <p class="label" style="margin:0">Participants</p>
    <span class="small muted">
      <?= (int) $event['participant_count'] ?> of <?= (int) $event['capacity'] ?>
    </span>
  </div>

  <div class="step-bar mb-2" style="background:var(--border)">
    <div style="height:100%;width:<?= $percent ?>%;background:var(--accent);border-radius:2px"></div>
  </div>

  <?php if (!$participants): ?>
    <p class="small muted" style="margin:0">No one has registered yet.</p>
  <?php else: ?>
    <div class="row row-wrap" style="gap:8px">
      <?php foreach ($participants as $participant): ?>
        <span class="pill">
          <span class="avatar avatar-sm">
            <?= e(initials($participant['first_name'], $participant['last_name'])) ?>
          </span>
          <?= e($participant['first_name'] . ' ' . mb_substr($participant['last_name'], 0, 1) . '.') ?>
        </span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="alert alert-info">
  <?= icon('info', 18) ?>
  <span>
    <?php if ($spotsLeft > 0 && in_array($event['status'], ['upcoming', 'active'], true)): ?>
      <strong><?= $spotsLeft ?> <?= pluralise($spotsLeft, 'spot') ?> left.</strong>
      To join, speak to Mango Drive Café staff — they will add you to the event and record
      your payment at the counter.
    <?php elseif ($spotsLeft === 0): ?>
      <strong>This event is full.</strong> Ask the front desk to be added to the reserve list.
    <?php else: ?>
      Registration for this event is closed.
    <?php endif; ?>
  </span>
</div>
