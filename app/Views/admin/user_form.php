<?php
$isEdit = $user !== null;
$action = $isEdit ? url('/admin/users/' . (int) $user['id']) : url('/admin/users');
?>

<div class="row mb-2" style="gap:10px">
  <a href="<?= url('/admin/users') ?>" class="icon-btn" style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div>
    <h1 style="margin:0"><?= $isEdit ? 'Edit account' : 'New user account' ?></h1>
    <p class="small muted" style="margin:0"><?= $isEdit ? e($user['username']) : 'Create a login for staff or a customer.' ?></p>
  </div>
</div>

<form method="post" action="<?= $action ?>" data-guard>
  <?= csrf_field() ?>

  <div class="card mb-2" style="max-width:560px">
    <div class="field">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" class="input <?= has_error('username') ? 'is-invalid' : '' ?>"
             value="<?= old('username', $user['username'] ?? '') ?>" required minlength="4" maxlength="50">
      <?= error_for('username') ?>
    </div>

    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" class="input <?= has_error('email') ? 'is-invalid' : '' ?>"
             value="<?= old('email', $user['email'] ?? '') ?>" required>
      <?= error_for('email') ?>
    </div>

    <div class="field">
      <label for="role">Role</label>
      <select id="role" name="role" class="select" required>
        <option value="customer" <?= ($user['role'] ?? '') === 'customer' ? 'selected' : '' ?>>Customer — books facilities</option>
        <option value="staff"    <?= ($user['role'] ?? '') === 'staff' ? 'selected' : '' ?>>Staff — counter operations</option>
        <option value="admin"    <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrator — full access</option>
      </select>
      <p class="field-help">Staff cannot manage users, facilities, settings or verify payments.</p>
    </div>

    <?php if (!$isEdit): ?>
      <div class="field">
        <label for="first_name">First name <span class="label-hint">(customers only)</span></label>
        <input type="text" id="first_name" name="first_name" class="input" value="<?= old('first_name') ?>">
      </div>

      <div class="field">
        <label for="last_name">Last name <span class="label-hint">(customers only)</span></label>
        <input type="text" id="last_name" name="last_name" class="input" value="<?= old('last_name') ?>">
      </div>

      <div class="field">
        <label for="password">Temporary password</label>
        <input type="password" id="password" name="password" class="input <?= has_error('password') ? 'is-invalid' : '' ?>"
               required minlength="8" autocomplete="new-password">
        <?= error_for('password') ?>
        <p class="field-help">The user is asked to change this at first login.</p>
      </div>

      <div class="field" style="margin-bottom:0">
        <label for="password_confirmation">Confirm password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="input"
               required minlength="8" autocomplete="new-password">
      </div>
    <?php else: ?>
      <p class="field-help">
        To change this account's password, use the reset button on the accounts list.
      </p>
    <?php endif; ?>
  </div>

  <div class="btn-group">
    <button type="submit" class="btn btn-primary" data-loading="Saving…">
      <?= $isEdit ? 'Save changes' : 'Create account' ?>
    </button>
    <a href="<?= url('/admin/users') ?>" class="btn btn-ghost">Cancel</a>
  </div>
</form>
