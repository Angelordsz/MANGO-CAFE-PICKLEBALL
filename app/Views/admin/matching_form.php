<div class="row mb-2" style="gap:10px">
  <a href="<?= url('/admin/matching') ?>" class="icon-btn" style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div>
    <h1 style="margin:0">New matching session</h1>
    <p class="small muted" style="margin:0">One session = one date, one time block, one skill level.</p>
  </div>
</div>

<form method="post" action="<?= url('/admin/matching') ?>" data-guard>
  <?= csrf_field() ?>

  <div class="card mb-2">
    <h2 class="mb-2">When</h2>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="match_date">Date</label>
        <input type="date" id="match_date" name="match_date" class="input <?= has_error('match_date') ? 'is-invalid' : '' ?>"
               value="<?= old('match_date', date('Y-m-d', strtotime('+1 day'))) ?>" min="<?= date('Y-m-d') ?>" required>
        <?= error_for('match_date') ?>
      </div>

      <div class="field">
        <label for="time_block">Time block</label>
        <select id="time_block" name="time_block" class="select" required>
          <option value="morning">Morning</option>
          <option value="afternoon" selected>Afternoon</option>
          <option value="evening">Evening</option>
        </select>
      </div>
    </div>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="start_time">Starts</label>
        <input type="time" id="start_time" name="start_time" class="input" value="<?= old('start_time', '16:00') ?>" step="1800" required>
      </div>
      <div class="field">
        <label for="end_time">Ends</label>
        <input type="time" id="end_time" name="end_time" class="input" value="<?= old('end_time', '18:00') ?>" step="1800" required>
      </div>
    </div>
  </div>

  <div class="card mb-2">
    <h2 class="mb-2">Who</h2>

    <div class="field">
      <label>Skill level</label>
      <p class="field-help mb-1" style="margin-top:0">Only players who declared this level can join.</p>
      <div class="segmented">
        <?php foreach ($levels as $i => $level): ?>
          <input type="radio" id="lvl<?= (int) $level['id'] ?>" name="skill_level_id" value="<?= (int) $level['id'] ?>"
                 <?= $i === 0 ? 'checked' : '' ?> required>
          <label for="lvl<?= (int) $level['id'] ?>"><?= e($level['level_name']) ?></label>
        <?php endforeach; ?>
      </div>
      <?= error_for('skill_level_id') ?>
    </div>

    <div class="field">
      <label>Format</label>
      <div class="segmented">
        <input type="radio" id="fmt_doubles" name="match_format" value="doubles" checked>
        <label for="fmt_doubles">Doubles (4 per match)</label>
        <input type="radio" id="fmt_singles" name="match_format" value="singles">
        <label for="fmt_singles">Singles (2 per match)</label>
      </div>
    </div>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="min_players">Minimum players</label>
        <input type="number" id="min_players" name="min_players" class="input" value="<?= old('min_players', 4) ?>" min="2" max="64" required>
        <p class="field-help">Below this, the session will not run.</p>
      </div>
      <div class="field">
        <label for="max_players">Maximum players</label>
        <input type="number" id="max_players" name="max_players" class="input" value="<?= old('max_players', 16) ?>" min="2" max="64" required>
      </div>
    </div>

    <div class="field" style="margin-bottom:0">
      <label for="notes">Notes <span class="label-hint">(optional)</span></label>
      <input type="text" id="notes" name="notes" class="input" maxlength="255"
             placeholder="Bring your own paddle" value="<?= old('notes') ?>">
    </div>
  </div>

  <div class="btn-group">
    <button type="submit" class="btn btn-primary" data-loading="Creating…">Create session</button>
    <a href="<?= url('/admin/matching') ?>" class="btn btn-ghost">Cancel</a>
  </div>
</form>
