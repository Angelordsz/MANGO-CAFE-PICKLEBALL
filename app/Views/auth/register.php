<h1>Create your account</h1>
<p class="muted mb-3">Register once, then reserve courts and join player matching.</p>

<form method="post" action="<?= url('/register') ?>" data-guard>
  <?= csrf_field() ?>

  <div class="grid grid-2" style="gap:0 12px">
    <div class="field">
      <label for="first_name">First name</label>
      <input type="text" id="first_name" name="first_name" class="input <?= has_error('first_name') ? 'is-invalid' : '' ?>"
             value="<?= old('first_name') ?>" required autocomplete="given-name" autofocus>
      <?= error_for('first_name') ?>
    </div>

    <div class="field">
      <label for="last_name">Last name</label>
      <input type="text" id="last_name" name="last_name" class="input <?= has_error('last_name') ? 'is-invalid' : '' ?>"
             value="<?= old('last_name') ?>" required autocomplete="family-name">
      <?= error_for('last_name') ?>
    </div>
  </div>

  <div class="field">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" class="input <?= has_error('username') ? 'is-invalid' : '' ?>"
           value="<?= old('username') ?>" required minlength="4" autocomplete="username">
    <?= error_for('username') ?>
    <p class="field-help">Letters, numbers, dots, dashes and underscores.</p>
  </div>

  <div class="field">
    <label for="email">Email</label>
    <input type="email" id="email" name="email" class="input <?= has_error('email') ? 'is-invalid' : '' ?>"
           value="<?= old('email') ?>" required autocomplete="email">
    <?= error_for('email') ?>
  </div>

  <div class="field">
    <label for="phone_number">Mobile number</label>
    <input type="tel" id="phone_number" name="phone_number" class="input <?= has_error('phone_number') ? 'is-invalid' : '' ?>"
           value="<?= old('phone_number') ?>" required placeholder="09XX XXX XXXX" autocomplete="tel">
    <?= error_for('phone_number') ?>
    <p class="field-help">The café uses this to confirm your reservation.</p>
  </div>

  <div class="grid grid-2" style="gap:0 12px">
    <div class="field">
      <label for="gender">Gender <span class="label-hint">(optional)</span></label>
      <select id="gender" name="gender" class="select">
        <option value="prefer_not_to_say">Prefer not to say</option>
        <option value="male"   <?= old('gender') === 'male' ? 'selected' : '' ?>>Male</option>
        <option value="female" <?= old('gender') === 'female' ? 'selected' : '' ?>>Female</option>
        <option value="other"  <?= old('gender') === 'other' ? 'selected' : '' ?>>Other</option>
      </select>
    </div>

    <div class="field">
      <label for="birthdate">Birthdate <span class="label-hint">(optional)</span></label>
      <input type="date" id="birthdate" name="birthdate" class="input <?= has_error('birthdate') ? 'is-invalid' : '' ?>"
             value="<?= old('birthdate') ?>" max="<?= date('Y-m-d') ?>">
      <?= error_for('birthdate') ?>
    </div>
  </div>

  <div class="field">
    <label>Your skill level</label>
    <p class="field-help mb-1" style="margin-top:0">
      Used to pair you with players of similar ability. You can change it later.
    </p>
    <div class="segmented">
      <?php foreach ($levels as $i => $level): ?>
        <input type="radio" id="skill<?= (int) $level['id'] ?>" name="skill_level_id"
               value="<?= (int) $level['id'] ?>"
               <?= (string) old('skill_level_id') === (string) $level['id'] || (old('skill_level_id') === '' && $i === 0) ? 'checked' : '' ?>
               required>
        <label for="skill<?= (int) $level['id'] ?>" title="<?= e($level['description']) ?>">
          <?= e($level['level_name']) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <?= error_for('skill_level_id') ?>
  </div>

  <div class="field">
    <label for="preferred_time">Usual playing time</label>
    <select id="preferred_time" name="preferred_time" class="select">
      <option value="any">Any time</option>
      <option value="morning"   <?= old('preferred_time') === 'morning' ? 'selected' : '' ?>>Morning</option>
      <option value="afternoon" <?= old('preferred_time') === 'afternoon' ? 'selected' : '' ?>>Afternoon</option>
      <option value="evening"   <?= old('preferred_time') === 'evening' ? 'selected' : '' ?>>Evening</option>
    </select>
  </div>

  <div class="grid grid-2" style="gap:0 12px">
    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" class="input <?= has_error('password') ? 'is-invalid' : '' ?>"
             required minlength="8" autocomplete="new-password">
      <?= error_for('password') ?>
    </div>

    <div class="field">
      <label for="password_confirmation">Confirm password</label>
      <input type="password" id="password_confirmation" name="password_confirmation" class="input"
             required minlength="8" autocomplete="new-password">
    </div>
  </div>

  <div class="field">
    <label class="check">
      <input type="checkbox" name="terms" value="1" required>
      <span>I agree to Mango Drive Café's reservation and cancellation policy.</span>
    </label>
    <?= error_for('terms') ?>
  </div>

  <button type="submit" class="btn btn-primary btn-block btn-lg" data-loading="Creating account…">
    Create account
  </button>
</form>

<p class="text-center mt-3 small">
  Already registered? <a href="<?= url('/login') ?>" style="font-weight:700">Log in</a>
</p>
