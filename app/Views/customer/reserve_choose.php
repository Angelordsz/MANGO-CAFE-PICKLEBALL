<h1>Make a reservation</h1>
<p class="muted mb-3">What would you like to book?</p>

<div class="grid grid-2 mb-3">
  <a href="<?= url('/reserve/court') ?>" class="card card-lg card-link">
    <span class="empty-icon" style="background:var(--lime-200);color:var(--lime-700);margin:0 0 12px">
      <?= icon('court', 26) ?>
    </span>
    <h2 style="margin:0 0 .25rem">Pickleball court</h2>
    <p class="small muted mb-2">
      Hourly court rental. Bring your own group, or sign up for player matching afterwards.
    </p>
    <div class="row row-wrap" style="gap:6px">
      <?php foreach ($courts as $court): ?>
        <span class="pill tiny">
          <?= e($court['name']) ?>
          <?php if ($court['slots_open'] > 0): ?>
            · <?= (int) $court['slots_open'] ?> open
          <?php endif; ?>
        </span>
      <?php endforeach; ?>
    </div>
    <p class="mt-2 mb-0" style="font-weight:700;color:var(--lime-600)">
      Book a court <?= icon('arrow-right', 14) ?>
    </p>
  </a>

  <a href="<?= url('/reserve/hall') ?>" class="card card-lg card-link">
    <span class="empty-icon" style="background:var(--lime-200);color:var(--lime-700);margin:0 0 12px">
      <?= icon('hall', 26) ?>
    </span>
    <h2 style="margin:0 0 .25rem">Function hall</h2>
    <p class="small muted mb-2">
      For birthdays, meetings, team events and gatherings. Minimum booking applies.
    </p>
    <div class="row row-wrap" style="gap:6px">
      <?php foreach ($halls as $hall): ?>
        <span class="pill tiny">
          <?= e($hall['name']) ?> · up to <?= (int) $hall['capacity'] ?> pax
        </span>
      <?php endforeach; ?>
    </div>
    <p class="mt-2 mb-0" style="font-weight:700;color:var(--lime-600)">
      Book the hall <?= icon('arrow-right', 14) ?>
    </p>
  </a>
</div>

<div class="alert alert-info">
  <?= icon('info', 18) ?>
  <span>
    Reservations are confirmed by Mango Drive Café staff. You will be notified once approved,
    and you pay at the counter when you arrive.
  </span>
</div>
