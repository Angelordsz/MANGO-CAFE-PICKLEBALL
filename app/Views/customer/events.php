<h1 class="mb-1">Events</h1>
<p class="muted mb-3">Clinics, leagues and social play at Mango Drive Café.</p>

<?php if (!$events): ?>
  <div class="card">
    <div class="empty">
      <span class="empty-icon"><?= icon('trophy', 26) ?></span>
      <h3>No events scheduled</h3>
      <p class="small">Check back soon, or reserve a court in the meantime.</p>
    </div>
  </div>
<?php else: ?>
  <div class="grid grid-3">
    <?php foreach ($events as $event): ?>
      <?php $spotsLeft = max(0, (int) $event['capacity'] - (int) $event['participant_count']); ?>
      <a href="<?= url('/events/' . (int) $event['id']) ?>" class="card card-flush card-link">
        <div class="thumb thumb-wide">
          <?php if ($event['image_path']): ?>
            <img src="<?= e(uploaded($event['image_path'])) ?>" alt=""
                 style="width:100%;height:100%;object-fit:cover">
          <?php else: ?>
            <?= icon('trophy', 34) ?>
          <?php endif; ?>
        </div>

        <div class="card-body">
          <div class="row-between mb-1">
            <span class="badge badge-accent"><?= e(label($event['event_type'])) ?></span>
            <span class="badge badge-<?= status_badge($event['status']) ?>"><?= e(label($event['status'])) ?></span>
          </div>

          <h3 style="margin:0 0 .25rem"><?= e($event['title']) ?></h3>

          <p class="small muted mb-1">
            <?= icon('clock', 13) ?> <?= e(fdate($event['start_datetime'], 'D, M j · g:i A')) ?>
          </p>
          <?php if ($event['court_name']): ?>
            <p class="small muted mb-1"><?= icon('court', 13) ?> <?= e($event['court_name']) ?></p>
          <?php endif; ?>

          <div class="row-between small mt-1">
            <span class="<?= $spotsLeft === 0 ? 'strong' : 'muted' ?>">
              <?= $spotsLeft > 0 ? $spotsLeft . ' ' . pluralise($spotsLeft, 'spot') . ' left' : 'Full' ?>
            </span>
            <strong><?= $event['fee'] > 0 ? money($event['fee']) : 'Free' ?></strong>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
