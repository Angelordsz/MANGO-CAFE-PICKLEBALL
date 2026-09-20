<div class="card card-lg mb-3">
  <h1>About this application</h1>
  <p class="muted">
    The Online Pickleball Player Matching and Function Hall and Court Reservation
    Management Application for Mango Drive Café.
  </p>
</div>

<div class="grid grid-2 mb-3">
  <div class="card">
    <h2 class="mb-2">What it does</h2>
    <div class="list-item">
      <span class="thumb" style="width:36px;height:36px"><?= icon('calendar', 16) ?></span>
      <div class="grow">
        <strong class="small" style="display:block">Online reservations</strong>
        <span class="tiny muted">Book pickleball courts and the function hall with live availability.</span>
      </div>
    </div>
    <div class="list-item">
      <span class="thumb" style="width:36px;height:36px"><?= icon('shuffle', 16) ?></span>
      <div class="grow">
        <strong class="small" style="display:block">Automatic player matching</strong>
        <span class="tiny muted">Register with a date and skill level and get paired with partners and opponents.</span>
      </div>
    </div>
    <div class="list-item">
      <span class="thumb" style="width:36px;height:36px"><?= icon('clipboard', 16) ?></span>
      <div class="grow">
        <strong class="small" style="display:block">Centralised records</strong>
        <span class="tiny muted">One database for players, reservations and payments, with reports for management.</span>
      </div>
    </div>
  </div>

  <div class="card">
    <h2 class="mb-2">How booking works</h2>
    <ol class="small muted" style="padding-left:1.1rem;line-height:2">
      <li>Create an account and set your skill level.</li>
      <li>Pick a facility, a date and one or more hourly slots.</li>
      <li>Submit the request — your slot is held straight away.</li>
      <li>Mango Drive Café confirms it and you get a notification.</li>
      <li>Pay at the counter when you arrive.</li>
    </ol>

    <div class="alert alert-info mt-2">
      <?= icon('info', 18) ?>
      <span class="small">
        No payment is taken online. The café records your payment against the booking
        when you settle at the counter.
      </span>
    </div>
  </div>
</div>

<div class="card text-center">
  <h2>Ready to play?</h2>
  <p class="muted mb-2">Create an account and reserve your first slot.</p>
  <div class="btn-group" style="justify-content:center">
    <a href="<?= url('/register') ?>" class="btn btn-primary">Create an account</a>
    <a href="<?= url('/availability') ?>" class="btn btn-outline">See availability</a>
  </div>
</div>
