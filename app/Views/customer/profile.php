<?php
$days        = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$selectedDay = array_filter(explode(',', (string) ($player['preferred_days'] ?? '')), 'strlen');
?>

<h1 class="mb-2">My profile</h1>

<div class="card card-lg mb-3">
  <div class="row mb-2" style="gap:14px">
    <?php if (!empty($player['avatar_path'])): ?>
      <img src="<?= e(uploaded($player['avatar_path'])) ?>" alt="" class="avatar avatar-lg">
    <?php else: ?>
      <span class="avatar avatar-lg">
        <?= e(initials($player['first_name'] ?? '', $player['last_name'] ?? '')) ?>
      </span>
    <?php endif; ?>

    <div class="grow">
      <h2 style="margin:0"><?= e(trim(($player['first_name'] ?? '') . ' ' . ($player['last_name'] ?? ''))) ?></h2>
      <p class="small muted" style="margin:0">
        <?= e($player['player_code'] ?? '') ?> · <?= e($user['email']) ?>
      </p>
      <div class="row row-wrap mt-1" style="gap:6px">
        <span class="badge badge-accent"><?= e($player['level_name'] ?? 'Unrated') ?></span>
        <span class="pill tiny"><?= e(label($player['preferred_time'] ?? 'any')) ?> player</span>
      </div>
    </div>
  </div>

  <form method="post" action="<?= url('/profile/avatar') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="input-group">
      <input type="file" name="avatar" class="input" accept="image/jpeg,image/png,image/webp" required>
      <button type="submit" class="btn btn-outline btn-sm">Upload photo</button>
    </div>
    <p class="field-help">JPG, PNG or WEBP, up to 2 MB.</p>
  </form>
</div>

<?php if ($summary): ?>
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
<?php endif; ?>

<?php // ENHANCEMENT -- win/loss record. See docs/ENHANCEMENTS.md. ?>
<?php if (!empty($showStats) && (int) ($stats['games_played'] ?? 0) > 0): ?>
  <div class="card mb-3">
    <div class="row-between mb-2">
      <h2 style="margin:0">Match record</h2>
      <span class="badge badge-accent"><?= (int) $stats['win_rate'] ?>% win rate</span>
    </div>

    <div class="grid grid-3 mb-2">
      <div class="stat">
        <p class="stat-label">Played</p>
        <p class="stat-value"><?= (int) $stats['games_played'] ?></p>
      </div>
      <div class="stat">
        <p class="stat-label">Won</p>
        <p class="stat-value" style="color:var(--ok)"><?= (int) $stats['wins'] ?></p>
      </div>
      <div class="stat">
        <p class="stat-label">Lost</p>
        <p class="stat-value" style="color:var(--bad)"><?= (int) $stats['losses'] ?></p>
      </div>
    </div>

    <div class="step-bar" style="background:var(--bad);overflow:hidden">
      <div style="height:100%;width:<?= (int) $stats['win_rate'] ?>%;background:var(--ok)"></div>
    </div>

    <?php if (!empty($stats['last_played_at'])): ?>
      <p class="field-help mt-1">Last played <?= e(human_date($stats['last_played_at'])) ?>.</p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-head" style="margin:-16px -16px 16px">
    <h2>Personal details</h2>
  </div>

  <form method="post" action="<?= url('/profile') ?>" data-guard>
    <?= csrf_field() ?>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="first_name">First name</label>
        <input type="text" id="first_name" name="first_name" class="input <?= has_error('first_name') ? 'is-invalid' : '' ?>"
               value="<?= old('first_name', $player['first_name'] ?? '') ?>" required>
        <?= error_for('first_name') ?>
      </div>

      <div class="field">
        <label for="last_name">Last name</label>
        <input type="text" id="last_name" name="last_name" class="input <?= has_error('last_name') ? 'is-invalid' : '' ?>"
               value="<?= old('last_name', $player['last_name'] ?? '') ?>" required>
        <?= error_for('last_name') ?>
      </div>
    </div>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" class="input <?= has_error('email') ? 'is-invalid' : '' ?>"
               value="<?= old('email', $user['email']) ?>" required>
        <?= error_for('email') ?>
      </div>

      <div class="field">
        <label for="phone_number">Mobile number</label>
        <input type="tel" id="phone_number" name="phone_number" class="input <?= has_error('phone_number') ? 'is-invalid' : '' ?>"
               value="<?= old('phone_number', $player['phone_number'] ?? '') ?>" required>
        <?= error_for('phone_number') ?>
      </div>
    </div>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="gender">Gender</label>
        <select id="gender" name="gender" class="select">
          <?php foreach (['prefer_not_to_say' => 'Prefer not to say', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $text): ?>
            <option value="<?= $value ?>" <?= ($player['gender'] ?? '') === $value ? 'selected' : '' ?>><?= $text ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="birthdate">Birthdate</label>
        <input type="date" id="birthdate" name="birthdate" class="input"
               value="<?= old('birthdate', $player['birthdate'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
      </div>
    </div>

    <div class="field">
      <label for="address">Address <span class="label-hint">(optional)</span></label>
      <input type="text" id="address" name="address" class="input"
             value="<?= old('address', $player['address'] ?? '') ?>" maxlength="255">
    </div>

    <div class="field">
      <label>Skill level</label>
      <p class="field-help mb-1" style="margin-top:0">Used by the player matching module to pair you fairly.</p>
      <div class="segmented">
        <?php foreach ($levels as $level): ?>
          <input type="radio" id="lvl<?= (int) $level['id'] ?>" name="skill_level_id" value="<?= (int) $level['id'] ?>"
                 <?= (int) ($player['skill_level_id'] ?? 0) === (int) $level['id'] ? 'checked' : '' ?> required>
          <label for="lvl<?= (int) $level['id'] ?>" title="<?= e($level['description']) ?>">
            <?= e($level['level_name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
      <?= error_for('skill_level_id') ?>
    </div>

    <div class="field">
      <label for="preferred_time">Preferred playing time</label>
      <select id="preferred_time" name="preferred_time" class="select">
        <?php foreach (['any' => 'Any time', 'morning' => 'Morning', 'afternoon' => 'Afternoon', 'evening' => 'Evening'] as $value => $text): ?>
          <option value="<?= $value ?>" <?= ($player['preferred_time'] ?? 'any') === $value ? 'selected' : '' ?>><?= $text ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label>Days you usually play</label>
      <div class="segmented">
        <?php foreach ($days as $index => $dayName): ?>
          <input type="checkbox" id="day<?= $index ?>" name="preferred_days[]" value="<?= $index ?>"
                 <?= in_array((string) $index, $selectedDay, true) ? 'checked' : '' ?>>
          <label for="day<?= $index ?>"><?= $dayName ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <button type="submit" class="btn btn-primary" data-loading="Saving…">Save changes</button>
  </form>
</div>

<div class="card mb-3">
  <div class="card-head" style="margin:-16px -16px 16px">
    <h2>Change password</h2>
  </div>

  <form method="post" action="<?= url('/profile/password') ?>" data-guard>
    <?= csrf_field() ?>

    <div class="field">
      <label for="current_password">Current password</label>
      <input type="password" id="current_password" name="current_password" class="input" required autocomplete="current-password">
    </div>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="new_password">New password</label>
        <input type="password" id="new_password" name="password" class="input" required minlength="8" autocomplete="new-password">
      </div>
      <div class="field">
        <label for="confirm_password">Confirm new password</label>
        <input type="password" id="confirm_password" name="password_confirmation" class="input" required minlength="8" autocomplete="new-password">
      </div>
    </div>

    <button type="submit" class="btn btn-outline">Update password</button>
  </form>
</div>

<?php if ($history): ?>
  <div class="card card-flush">
    <div class="card-head"><h2>Recent activity</h2></div>
    <div class="card-body" style="padding-top:0">
      <?php foreach ($history as $entry): ?>
        <div class="list-item">
          <span class="thumb" style="width:36px;height:36px">
            <?= icon(match ($entry['activity_type']) {
                'reservation'  => 'ticket',
                'match'        => 'shuffle',
                'event'        => 'trophy',
                'payment'      => 'money',
                default        => 'user',
            }, 16) ?>
          </span>
          <div class="grow">
            <p class="small" style="margin:0"><?= e($entry['description']) ?></p>
            <span class="tiny muted">
              <?= e(fdate($entry['activity_date'])) ?>
              <?= $entry['activity_time'] ? ' · ' . e(ftime($entry['activity_time'])) : '' ?>
            </span>
          </div>
          <?php if ((float) $entry['payment'] > 0): ?>
            <strong class="small"><?= money($entry['payment']) ?></strong>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
