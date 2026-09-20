<?php
$isEdit = $event !== null;
$action = $isEdit ? url('/admin/events/' . (int) $event['id']) : url('/admin/events');
?>

<div class="row mb-2" style="gap:10px">
  <a href="<?= url($isEdit ? '/admin/events/' . (int) $event['id'] : '/admin/events') ?>" class="icon-btn"
     style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <div>
    <h1 style="margin:0"><?= $isEdit ? 'Edit event' : 'Create new event' ?></h1>
    <p class="small muted" style="margin:0">Fields follow Figure 3.6 of the design document.</p>
  </div>
</div>

<form method="post" action="<?= $action ?>" enctype="multipart/form-data" data-guard>
  <?= csrf_field() ?>

  <div class="card mb-2">
    <h2 class="mb-2">Event information</h2>

    <div class="field">
      <label for="title">Event title</label>
      <input type="text" id="title" name="title" class="input <?= has_error('title') ? 'is-invalid' : '' ?>"
             value="<?= old('title', $event['title'] ?? '') ?>" required maxlength="150" autofocus>
      <?= error_for('title') ?>
    </div>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="event_type">Event type</label>
        <select id="event_type" name="event_type" class="select" required>
          <?php foreach (['open_play','clinic','league','exhibition','social','private'] as $option): ?>
            <option value="<?= $option ?>" <?= ($event['event_type'] ?? '') === $option ? 'selected' : '' ?>>
              <?= e(label($option)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status" class="select" required>
          <?php foreach (['draft','upcoming','active','ongoing','completed','cancelled'] as $option): ?>
            <option value="<?= $option ?>" <?= ($event['status'] ?? 'upcoming') === $option ? 'selected' : '' ?>>
              <?= e(label($option)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="field">
      <label for="description">Description</label>
      <textarea id="description" name="description" class="textarea" rows="4"
                maxlength="5000"><?= old('description', $event['description'] ?? '') ?></textarea>
    </div>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="location">Location</label>
        <input type="text" id="location" name="location" class="input" maxlength="150" required
               value="<?= old('location', $event['location'] ?? 'Mango Drive Cafe') ?>">
      </div>

      <div class="field">
        <label for="court_id">Court <span class="label-hint">(optional)</span></label>
        <select id="court_id" name="court_id" class="select">
          <option value="">Not court-specific</option>
          <?php foreach ($courts as $court): ?>
            <option value="<?= (int) $court['id'] ?>" <?= (int) ($event['court_id'] ?? 0) === (int) $court['id'] ? 'selected' : '' ?>>
              <?= e($court['court_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="start_datetime">Starts</label>
        <input type="datetime-local" id="start_datetime" name="start_datetime"
               class="input <?= has_error('start_datetime') ? 'is-invalid' : '' ?>" required
               value="<?= old('start_datetime', $event ? date('Y-m-d\TH:i', strtotime($event['start_datetime'])) : date('Y-m-d\TH:i', strtotime('+1 day 16:00'))) ?>">
        <?= error_for('start_datetime') ?>
      </div>

      <div class="field">
        <label for="end_datetime">Ends</label>
        <input type="datetime-local" id="end_datetime" name="end_datetime"
               class="input <?= has_error('end_datetime') ? 'is-invalid' : '' ?>" required
               value="<?= old('end_datetime', $event ? date('Y-m-d\TH:i', strtotime($event['end_datetime'])) : date('Y-m-d\TH:i', strtotime('+1 day 18:00'))) ?>">
        <?= error_for('end_datetime') ?>
      </div>
    </div>

    <div class="grid grid-3" style="gap:0 12px">
      <div class="field">
        <label for="capacity">Capacity / limit</label>
        <input type="number" id="capacity" name="capacity" class="input" min="1" max="500" required
               value="<?= old('capacity', $event['capacity'] ?? 20) ?>">
      </div>

      <div class="field">
        <label for="fee">Fee per participant</label>
        <input type="number" id="fee" name="fee" class="input" step="0.01" min="0" required
               value="<?= old('fee', $event['fee'] ?? '0.00') ?>">
      </div>

      <div class="field">
        <label for="skill_level_id">Skill level</label>
        <select id="skill_level_id" name="skill_level_id" class="select">
          <option value="">Open to all levels</option>
          <?php foreach ($levels as $level): ?>
            <option value="<?= (int) $level['id'] ?>" <?= (int) ($event['skill_level_id'] ?? 0) === (int) $level['id'] ? 'selected' : '' ?>>
              <?= e($level['level_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="field" style="margin-bottom:0">
      <label for="image">Event image <span class="label-hint">(optional)</span></label>
      <input type="file" id="image" name="image" class="input" accept="image/jpeg,image/png,image/webp">
      <p class="field-help">JPG, PNG or WEBP, up to 3 MB.</p>
      <?php if (!empty($event['image_path'])): ?>
        <img src="<?= e(uploaded($event['image_path'])) ?>" alt=""
             style="height:90px;border-radius:var(--radius-sm);margin-top:8px">
      <?php endif; ?>
    </div>
  </div>

  <div class="btn-group">
    <button type="submit" class="btn btn-primary" data-loading="Saving…">
      <?= $isEdit ? 'Save changes' : 'Save event' ?>
    </button>
    <a href="<?= url('/admin/events') ?>" class="btn btn-ghost">Cancel</a>
  </div>
</form>
