<h1>Reset your password</h1>
<p class="muted mb-3">Enter the email on your account and we will create a reset link.</p>

<form method="post" action="<?= url('/forgot-password') ?>" data-guard>
  <?= csrf_field() ?>
  <div class="field">
    <label for="email">Email address</label>
    <input type="email" id="email" name="email" class="input" value="<?= old('email') ?>" required autofocus>
  </div>
  <button type="submit" class="btn btn-primary btn-block" data-loading="Working…">Create reset link</button>
</form>

<p class="text-center mt-3 small">
  <a href="<?= url('/login') ?>" style="font-weight:700">Back to log in</a>
</p>
