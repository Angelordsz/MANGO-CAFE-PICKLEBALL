<div class="row mb-2" style="gap:10px">
  <a href="<?= url('/admin/players/' . (int) $player['id']) ?>" class="icon-btn"
     style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div>
    <h1 style="margin:0">Edit player record</h1>
    <p class="small muted" style="margin:0"><?= e($player['player_code']) ?></p>
  </div>
</div>

<form method="post" action="<?= url('/admin/players/' . (int) $player['id']) ?>" data-guard>
  <?= csrf_field() ?>

  <div class="card mb-2">
    <h2 class="mb-2">Personal details</h2>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="first_name">First name</label>
        <input type="text" id="first_name" name="first_name" class="input <?= has_error('first_name') ? 'is-invalid' : '' ?>"
               value="<?= old('first_name', $player['first_name']) ?>" required>
        <?= error_for('first_name') ?>
      </div>
      <div class="field">
        <label for="last_name">Last name</label>
        <input type="text" id="last_name" name="last_name" class="input <?= has_error('last_name') ? 'is-invalid' : '' ?>"
               value="<?= old('last_name', $player['last_name']) ?>" required>
        <?= error_for('last_name') ?>
      </div>
    </div>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="phone_number">Contact number</label>
        <input type="tel" id="phone_number" name="phone_number" class="input"
               value="<?= old('phone_number', $player['phone_number'] ?? '') ?>">
        <?= error_for('phone_number') ?>
      </div>
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" class="input"
               value="<?= old('email', $player['email'] ?? '') ?>">
        <?= error_for('email') ?>
      </div>
    </div>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="gender">Gender</label>
        <select id="gender" name="gender" class="select">
          <?php foreach (['prefer_not_to_say' => 'Prefer not to say', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $text): ?>
            <option value="<?= $value ?>" <?= $player['gender'] === $value ? 'selected' : '' ?>><?= $text ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="birthdate">Birthdate</label>
        <input type="date" id="birthdate" name="birthdate" class="input"
               value="<?= old('birthdate', $player['birthdate'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
      </div>
    </div>

    <div class="field" style="margin-bottom:0">
      <label for="address">Address</label>
      <input type="text" id="address" name="address" class="input" maxlength="255"
             value="<?= old('address', $player['address'] ?? '') ?>">
    </div>
  </div>

  <div class="card mb-2">
    <h2 class="mb-2">Playing details</h2>

    <div class="field">
      <label>Skill level</label>
      <div class="segmented">
        <?php foreach ($levels as $level): ?>
          <input type="radio" id="lvl<?= (int) $level['id'] ?>" name="skill_level_id" value="<?= (int) $level['id'] ?>"
                 <?= (int) $player['skill_level_id'] === (int) $level['id'] ? 'checked' : '' ?> required>
          <label for="lvl<?= (int) $level['id'] ?>"><?= e($level['level_name']) ?></label>
        <?php endforeach; ?>
      </div>
      <?= error_for('skill_level_id') ?>
    </div>

    <div class="field">
      <label for="preferred_time">Preferred playing time</label>
      <select id="preferred_time" name="preferred_time" class="select">
        <?php foreach (['any' => 'Any time', 'morning' => 'Morning', 'afternoon' => 'Afternoon', 'evening' => 'Evening'] as $value => $text): ?>
          <option value="<?= $value ?>" <?= $player['preferred_time'] === $value ? 'selected' : '' ?>><?= $text ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field" style="margin-bottom:0">
      <label for="notes">Staff notes</label>
      <textarea id="notes" name="notes" class="textarea" rows="3" maxlength="1000"><?= old('notes', $player['notes'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="btn-group">
    <button type="submit" class="btn btn-primary" data-loading="Saving…">Save changes</button>
    <a href="<?= url('/admin/players/' . (int) $player['id']) ?>" class="btn btn-ghost">Cancel</a>
  </div>
</form>
