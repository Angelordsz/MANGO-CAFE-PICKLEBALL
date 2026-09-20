<div class="row mb-2" style="gap:10px">
  <a href="<?= url('/admin/players') ?>" class="icon-btn" style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div>
    <h1 style="margin:0">Walk-in registration</h1>
    <p class="small muted" style="margin:0">Record a player who came to the counter without an account.</p>
  </div>
</div>

<div class="alert alert-info mb-3">
  <?= icon('info', 18) ?>
  <span>
    This creates a player record with <strong>no login</strong>. You can book courts, add them to
    matching sessions and record payments against it. They can register online later and the
    café can link the records.
  </span>
</div>

<form method="post" action="<?= url('/admin/players/walk-in') ?>" data-guard>
  <?= csrf_field() ?>

  <div class="card mb-2">
    <h2 class="mb-2">Player details</h2>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="first_name">First name</label>
        <input type="text" id="first_name" name="first_name" class="input <?= has_error('first_name') ? 'is-invalid' : '' ?>"
               value="<?= old('first_name') ?>" required autofocus>
        <?= error_for('first_name') ?>
      </div>

      <div class="field">
        <label for="last_name">Last name</label>
        <input type="text" id="last_name" name="last_name" class="input <?= has_error('last_name') ? 'is-invalid' : '' ?>"
               value="<?= old('last_name') ?>" required>
        <?= error_for('last_name') ?>
      </div>
    </div>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="phone_number">Contact number</label>
        <input type="tel" id="phone_number" name="phone_number" class="input <?= has_error('phone_number') ? 'is-invalid' : '' ?>"
               value="<?= old('phone_number') ?>" required placeholder="09XX XXX XXXX">
        <?= error_for('phone_number') ?>
      </div>

      <div class="field">
        <label for="email">Email <span class="label-hint">(optional)</span></label>
        <input type="email" id="email" name="email" class="input" value="<?= old('email') ?>">
        <?= error_for('email') ?>
      </div>
    </div>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="gender">Gender</label>
        <select id="gender" name="gender" class="select">
          <option value="prefer_not_to_say">Prefer not to say</option>
          <option value="male">Male</option>
          <option value="female">Female</option>
          <option value="other">Other</option>
        </select>
      </div>

      <div class="field">
        <label for="age">Age <span class="label-hint">(optional)</span></label>
        <input type="number" id="age" name="age" class="input" min="5" max="100" value="<?= old('age') ?>">
        <?= error_for('age') ?>
      </div>
    </div>
  </div>

  <div class="card mb-2">
    <h2 class="mb-2">Playing details</h2>
    <p class="small muted mb-2">Needed so the matching module can pair this player fairly.</p>

    <div class="field">
      <label>Skill level</label>
      <div class="segmented">
        <?php foreach ($levels as $i => $level): ?>
          <input type="radio" id="lvl<?= (int) $level['id'] ?>" name="skill_level_id" value="<?= (int) $level['id'] ?>"
                 <?= $i === 0 ? 'checked' : '' ?> required>
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
        <option value="any">Any time</option>
        <option value="morning">Morning</option>
        <option value="afternoon">Afternoon</option>
        <option value="evening">Evening</option>
      </select>
    </div>

    <div class="field" style="margin-bottom:0">
      <label for="notes">Staff notes <span class="label-hint">(optional)</span></label>
      <textarea id="notes" name="notes" class="textarea" rows="2" maxlength="1000"
                placeholder="Anything worth remembering about this player"><?= old('notes') ?></textarea>
    </div>
  </div>

  <div class="card">
    <p class="label">After saving</p>
    <p class="small muted mb-2">Figure 3.5 branches here — choose what happens next.</p>

    <div class="btn-group">
      <button type="submit" class="btn btn-primary" data-loading="Saving…">
        <?= icon('check', 16) ?> Register only
      </button>
      <button type="submit" name="then_book" value="1" class="btn btn-outline">
        <?= icon('calendar', 16) ?> Register &amp; book a facility
      </button>
      <button type="submit" name="then_match" value="1" class="btn btn-outline">
        <?= icon('shuffle', 16) ?> Register &amp; add to matching
      </button>
    </div>
  </div>
</form>
