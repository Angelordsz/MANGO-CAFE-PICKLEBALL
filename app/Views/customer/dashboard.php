<?php
$hour  = (int) date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$next  = $upcoming[0] ?? null;
?>

<div class="row-between mb-2">
  <div>
    <p class="small muted" style="margin:0"><?= $greet ?>,</p>
    <h1 style="margin:0"><?= e($player['first_name']) ?></h1>
  </div>
  <span class="badge badge-accent"><?= e($player['level_name'] ?? 'Unrated') ?></span>
</div>

<?php if ($next): ?>
  <a href="<?= url('/reservations/' . $next['reservation_code']) ?>" class="hero animate mb-3" style="display:block">
    <div style="position:relative;z-index:1">
      <div class="row-between mb-1">
        <span class="badge badge-<?= status_badge($next['status']) ?>"><?= e(label($next['status'])) ?></span>
        <span class="small" style="color:rgba(255,255,255,.75)"><?= e($next['reservation_code']) ?></span>
      </div>
      <p class="small" style="color:rgba(255,255,255,.75);margin:0">Your next reservation</p>
      <h2 style="color:#fff;margin:.15rem 0 .35rem"><?= e($next['facility_name']) ?></h2>
      <p style="margin:0">
        <?= icon('calendar', 15) ?> <?= e(human_date($next['reservation_date'])) ?>
        &nbsp;·&nbsp;
        <?= icon('clock', 15) ?> <?= e(ftimerange($next['start_time'], $next['end_time'])) ?>
      </p>
      <?php if ($next['status'] === 'pending'): ?>
        <p class="small mt-1" style="color:var(--lime-300);margin-bottom:0">
          Waiting for the café to confirm. You will get a notification.
        </p>
      <?php endif; ?>
    </div>
  </a>
<?php else: ?>
  <section class="card card-lg mb-3 text-center animate">
    <span class="empty-icon"><?= icon('calendar', 26) ?></span>
    <h3>No upcoming reservations</h3>
    <p class="muted small mb-2">Reserve a court or the function hall to get started.</p>
    <a href="<?= url('/reserve') ?>" class="btn btn-primary">Reserve now</a>
  </section>
<?php endif; ?>

<!-- Quick actions -->
<div class="grid grid-4 mb-3">
  <a href="<?= url('/reserve/court') ?>" class="card card-link text-center" style="padding:14px 8px">
    <span class="empty-icon" style="width:42px;height:42px;margin-bottom:8px;background:var(--lime-200);color:var(--lime-700)">
      <?= icon('court', 20) ?>
    </span>
    <strong class="small">Book court</strong>
  </a>
  <a href="<?= url('/reserve/hall') ?>" class="card card-link text-center" style="padding:14px 8px">
    <span class="empty-icon" style="width:42px;height:42px;margin-bottom:8px;background:var(--lime-200);color:var(--lime-700)">
      <?= icon('hall', 20) ?>
    </span>
    <strong class="small">Book hall</strong>
  </a>
  <a href="<?= url('/matching') ?>" class="card card-link text-center" style="padding:14px 8px">
    <span class="empty-icon" style="width:42px;height:42px;margin-bottom:8px;background:var(--lime-200);color:var(--lime-700)">
      <?= icon('shuffle', 20) ?>
    </span>
    <strong class="small">Find a game</strong>
  </a>
  <a href="<?= url('/availability') ?>" class="card card-link text-center" style="padding:14px 8px">
    <span class="empty-icon" style="width:42px;height:42px;margin-bottom:8px;background:var(--lime-200);color:var(--lime-700)">
      <?= icon('calendar', 20) ?>
    </span>
    <strong class="small">Schedule</strong>
  </a>
</div>

<!-- Happening today -->
<div class="section-head">
  <h2>Available today</h2>
  <a href="<?= url('/availability') ?>">Full schedule <?= icon('chevron-right', 14) ?></a>
</div>

<div class="grid grid-3 mb-3">
  <?php foreach (array_merge($courtsToday, $hallsToday) as $facility): ?>
    <?php $isOpen = $facility['slots_open'] > 0 && $facility['status'] === 'available'; ?>
    <a href="<?= url($facility['type'] === 'court' ? '/reserve/court?facility=' . $facility['id'] : '/reserve/hall?facility=' . $facility['id']) ?>"
       class="card card-link">
      <div class="row-between mb-1">
        <div class="row" style="gap:9px">
          <span class="thumb" style="width:38px;height:38px">
            <?= icon($facility['type'] === 'court' ? 'court' : 'hall', 18) ?>
          </span>
          <div>
            <strong style="display:block"><?= e($facility['name']) ?></strong>
            <span class="tiny muted"><?= money($facility['rate']) ?>/hr</span>
          </div>
        </div>
      </div>
      <?php if ($isOpen): ?>
        <span class="badge badge-ok"><?= (int) $facility['slots_open'] ?> <?= pluralise((int) $facility['slots_open'], 'slot') ?> open</span>
      <?php elseif ($facility['status'] !== 'available'): ?>
        <span class="badge badge-muted"><?= e(label($facility['status'])) ?></span>
      <?php else: ?>
        <span class="badge badge-bad">Fully booked</span>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<!-- Player matching -->
<?php if ($matchSignups): ?>
  <div class="section-head">
    <h2>Your matching sign-ups</h2>
    <a href="<?= url('/matching/results') ?>">Results <?= icon('chevron-right', 14) ?></a>
  </div>

  <div class="card card-flush mb-3">
    <?php foreach ($matchSignups as $signup): ?>
      <div class="list-item" style="padding-left:16px;padding-right:16px">
        <span class="thumb" style="width:44px;height:44px"><?= icon('shuffle', 19) ?></span>
        <div class="grow">
          <strong><?= e($signup['level_name']) ?> · <?= e(label($signup['match_format'])) ?></strong>
          <p class="small muted" style="margin:0">
            <?= e(human_date($signup['match_date'])) ?> · <?= e(ftimerange($signup['start_time'], $signup['end_time'])) ?>
          </p>
        </div>
        <span class="badge badge-<?= status_badge($signup['status']) ?>"><?= e(label($signup['status'])) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
<?php elseif ($openPools): ?>
  <div class="section-head">
    <h2>Find a game</h2>
    <a href="<?= url('/matching') ?>">See all <?= icon('chevron-right', 14) ?></a>
  </div>

  <div class="card card-flush mb-3">
    <?php foreach ($openPools as $pool): ?>
      <div class="list-item" style="padding-left:16px;padding-right:16px">
        <span class="thumb" style="width:44px;height:44px"><?= icon('paddle', 19) ?></span>
        <div class="grow">
          <strong><?= e($pool['level_name']) ?> · <?= e(label($pool['time_block'])) ?></strong>
          <p class="small muted" style="margin:0">
            <?= e(human_date($pool['match_date'])) ?> · <?= (int) $pool['signups'] ?>/<?= (int) $pool['max_players'] ?> registered
          </p>
        </div>
        <a href="<?= url('/matching') ?>" class="btn btn-sm btn-outline">Join</a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Recent matches -->
<?php if ($recentMatches): ?>
  <div class="section-head">
    <h2>Your matches</h2>
    <a href="<?= url('/matching/results') ?>">All <?= icon('chevron-right', 14) ?></a>
  </div>

  <div class="grid grid-3 mb-3">
    <?php foreach ($recentMatches as $match): ?>
      <div class="card">
        <div class="row-between mb-1">
          <span class="badge badge-info">Match <?= (int) $match['match_no'] ?></span>
          <span class="tiny muted"><?= e(fdate($match['match_date'])) ?></span>
        </div>
        <p class="small muted mb-1">
          <?= icon('court', 13) ?> <?= e($match['court_name'] ?? 'Court TBA') ?>
          · <?= e(ftime($match['start_time'])) ?>
        </p>
        <div class="row row-wrap" style="gap:5px">
          <?php foreach ($match['players'] as $mp): ?>
            <span class="pill tiny <?= (int) $mp['team'] === (int) $match['my_team'] ? '' : 'muted' ?>">
              <?= e($mp['first_name']) ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Events -->
<?php if ($events): ?>
  <div class="section-head">
    <h2>Upcoming events</h2>
    <a href="<?= url('/events') ?>">All <?= icon('chevron-right', 14) ?></a>
  </div>

  <div class="grid grid-3 mb-3">
    <?php foreach ($events as $event): ?>
      <a href="<?= url('/events/' . (int) $event['id']) ?>" class="card card-link">
        <span class="badge badge-accent mb-1"><?= e(label($event['event_type'])) ?></span>
        <h3 style="margin:0 0 .25rem"><?= e($event['title']) ?></h3>
        <p class="small muted mb-1"><?= e(fdate($event['start_datetime'], 'M j, g:i A')) ?></p>
        <div class="row-between small">
          <span><?= (int) $event['participant_count'] ?>/<?= (int) $event['capacity'] ?> joined</span>
          <strong><?= $event['fee'] > 0 ? money($event['fee']) : 'Free' ?></strong>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Your activity -->
<div class="section-head"><h2>Your activity</h2></div>
<div class="grid grid-4 mb-3">
  <div class="stat stat-accent">
    <p class="stat-label">Reservations</p>
    <p class="stat-value"><?= (int) $summary['reservations'] ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Matches</p>
    <p class="stat-value"><?= (int) $summary['matches_played'] ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Events</p>
    <p class="stat-value"><?= (int) $summary['events_joined'] ?></p>
  </div>
  <div class="stat">
    <p class="stat-label">Total paid</p>
    <p class="stat-value" style="font-size:1.25rem"><?= money($summary['total_paid']) ?></p>
  </div>
</div>
