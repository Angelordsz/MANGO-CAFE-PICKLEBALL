<h1>Welcome back</h1>
<p class="muted mb-3">Log in to reserve a court or check your matches.</p>

<form method="post" action="<?= url('/login') ?>" data-guard>
  <?= csrf_field() ?>

  <div class="field">
    <label for="identity">Username or email</label>
    <input type="text" id="identity" name="identity" class="input <?= has_error('identity') ? 'is-invalid' : '' ?>"
           value="<?= old('identity') ?>" required autocomplete="username" autofocus>
    <?= error_for('identity') ?>
  </div>

  <div class="field">
    <div class="row-between" style="margin-bottom:5px">
      <label for="password" style="margin:0">Password</label>
      <a href="<?= url('/forgot-password') ?>" class="small" style="font-weight:600">Forgot?</a>
    </div>
    <input type="password" id="password" name="password" class="input <?= has_error('password') ? 'is-invalid' : '' ?>"
           required autocomplete="current-password">
    <?= error_for('password') ?>
  </div>

  <button type="submit" class="btn btn-primary btn-block btn-lg mt-2" data-loading="Signing in…">
    Log in
  </button>
</form>

<div class="divider-text">New to Mango Drive?</div>

<a href="<?= url('/register') ?>" class="btn btn-outline btn-block">Create an account</a>

<p class="tiny muted text-center mt-3">
  Booking a court does not require payment online — you settle at the counter.
</p>
