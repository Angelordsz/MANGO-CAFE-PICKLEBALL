<h1>Choose a new password</h1>
<p class="muted mb-3">Pick something at least 8 characters long.</p>

<form method="post" action="<?= url('/reset-password/' . $token) ?>" data-guard>
  <?= csrf_field() ?>

  <div class="field">
    <label for="password">New password</label>
    <input type="password" id="password" name="password" class="input <?= has_error('password') ? 'is-invalid' : '' ?>"
           required minlength="8" autocomplete="new-password" autofocus>
    <?= error_for('password') ?>
  </div>

  <div class="field">
    <label for="password_confirmation">Confirm new password</label>
    <input type="password" id="password_confirmation" name="password_confirmation" class="input"
           required minlength="8" autocomplete="new-password">
  </div>

  <button type="submit" class="btn btn-primary btn-block" data-loading="Saving…">Update password</button>
</form>
