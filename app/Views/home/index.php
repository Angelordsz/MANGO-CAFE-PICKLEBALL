<section class="hero animate mb-3">
  <div style="position:relative;z-index:1;max-width:38rem">
    <span class="badge badge-accent mb-1">Mango Drive Café</span>
    <h1 style="font-size:2rem">Book a court.<br>Find a game.</h1>
    <p class="mb-3">
      Reserve pickleball courts and the function hall online — see real availability,
      skip the phone calls, and get matched with players at your level.
    </p>
    <div class="btn-group">
      <a href="<?= url('/register') ?>" class="btn btn-primary btn-lg">Get started</a>
      <a href="<?= url('/availability') ?>" class="btn btn-outline btn-lg" style="color:#fff;border-color:rgba(255,255,255,.4)">
        See availability
      </a>
    </div>
  </div>
</section>

<section class="grid grid-3 mb-3">
  <div class="card animate animate-1">
    <div class="row mb-1">
      <span class="brand-mark" style="background:var(--lime-200);color:var(--lime-700)"><?= icon('calendar', 18) ?></span>
      <h3 style="margin:0">Real availability</h3>
    </div>
    <p class="small muted" style="margin:0">
      Every open slot, shown live. One reservation per slot means no double bookings — ever.
    </p>
  </div>

  <div class="card animate animate-2">
    <div class="row mb-1">
      <span class="brand-mark" style="background:var(--lime-200);color:var(--lime-700)"><?= icon('shuffle', 18) ?></span>
      <h3 style="margin:0">Player matching</h3>
    </div>
    <p class="small muted" style="margin:0">
      Came without a partner? Pick a date and your skill level and we pair you up automatically.
    </p>
  </div>

  <div class="card animate animate-3">
    <div class="row mb-1">
      <span class="brand-mark" style="background:var(--lime-200);color:var(--lime-700)"><?= icon('hall', 18) ?></span>
      <h3 style="margin:0">Function hall</h3>
    </div>
    <p class="small muted" style="margin:0">
      Birthdays, meetings, team events — reserve the hall the same way you book a court.
    </p>
  </div>
</section>

<?php if ($courts): ?>
  <div class="section-head">
    <h2>Our courts</h2>
    <a href="<?= url('/availability') ?>">View schedule <?= icon('chevron-right', 14) ?></a>
  </div>

  <div class="grid grid-3 mb-3">
    <?php foreach ($courts as $court): ?>
      <article class="card card-flush card-link">
        <div class="thumb thumb-wide">
          <?php if ($court['image']): ?>
            <img src="<?= e(uploaded($court['image'])) ?>" alt="<?= e($court['name']) ?>"
                 style="width:100%;height:100%;object-fit:cover">
          <?php else: ?>
            <?= icon('court', 40) ?>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <div class="row-between mb-1">
            <h3 style="margin:0"><?= e($court['name']) ?></h3>
            <span class="badge badge-<?= status_badge($court['status']) ?>"><?= e(label($court['status'])) ?></span>
          </div>
          <p class="small muted mb-1">
            <?= e(label($court['court_type'])) ?><?= $court['surface'] ? ' · ' . e(ucfirst($court['surface'])) : '' ?>
          </p>
          <div class="row-between">
            <strong><?= money($court['rate']) ?><span class="small muted">/hour</span></strong>
            <?php if ($court['slots_open'] > 0): ?>
              <span class="pill"><?= (int) $court['slots_open'] ?> open today</span>
            <?php else: ?>
              <span class="pill muted">Fully booked today</span>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($halls): ?>
  <div class="section-head">
    <h2>Function hall</h2>
  </div>

  <div class="grid grid-2 mb-3">
    <?php foreach ($halls as $hall): ?>
      <article class="card">
        <div class="row-between mb-1">
          <h3 style="margin:0"><?= e($hall['name']) ?></h3>
          <span class="badge badge-<?= status_badge($hall['status']) ?>"><?= e(label($hall['status'])) ?></span>
        </div>
        <p class="small muted mb-1">Seats up to <?= (int) $hall['capacity'] ?> guests</p>
        <?php if ($hall['amenities']): ?>
          <div class="row row-wrap mb-1" style="gap:6px">
            <?php foreach (array_slice(array_map('trim', explode(',', $hall['amenities'])), 0, 4) as $amenity): ?>
              <span class="pill tiny"><?= e(ucfirst($amenity)) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <strong><?= money($hall['rate']) ?><span class="small muted">/hour</span></strong>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($openPools): ?>
  <div class="section-head">
    <h2>Open for player matching</h2>
  </div>

  <div class="card card-flush mb-3">
    <?php foreach ($openPools as $pool): ?>
      <div class="list-item" style="padding-left:16px;padding-right:16px">
        <span class="thumb" style="width:46px;height:46px"><?= icon('shuffle', 20) ?></span>
        <div class="grow">
          <strong><?= e($pool['level_name']) ?> · <?= e(label($pool['match_format'])) ?></strong>
          <p class="small muted" style="margin:0">
            <?= e(human_date($pool['match_date'])) ?> · <?= e(ftimerange($pool['start_time'], $pool['end_time'])) ?>
          </p>
        </div>
        <span class="pill"><?= (int) $pool['signups'] ?>/<?= (int) $pool['max_players'] ?></span>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($events): ?>
  <div class="section-head">
    <h2>Upcoming events</h2>
    <a href="<?= url('/events') ?>">All events <?= icon('chevron-right', 14) ?></a>
  </div>

  <div class="grid grid-3 mb-3">
    <?php foreach ($events as $event): ?>
      <a href="<?= url('/events/' . (int) $event['id']) ?>" class="card card-link">
        <span class="badge badge-accent mb-1"><?= e(label($event['event_type'])) ?></span>
        <h3 style="margin:0 0 .25rem"><?= e($event['title']) ?></h3>
        <p class="small muted mb-1">
          <?= icon('clock', 13) ?> <?= e(fdate($event['start_datetime'], 'M j, g:i A')) ?>
        </p>
        <div class="row-between small">
          <span><?= (int) $event['participant_count'] ?>/<?= (int) $event['capacity'] ?> joined</span>
          <strong><?= $event['fee'] > 0 ? money($event['fee']) : 'Free' ?></strong>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<section class="card card-lg text-center" style="background:var(--brand-surface);color:#fff;border:0">
  <h2 style="color:#fff">Ready to play?</h2>
  <p style="color:rgba(255,255,255,.75);max-width:44ch;margin:0 auto 1rem">
    Create an account in under a minute. Reserve your slot, pay at the counter when you arrive.
  </p>
  <a href="<?= url('/register') ?>" class="btn btn-primary btn-lg">Create an account</a>
</section>
