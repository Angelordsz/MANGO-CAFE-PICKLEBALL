<?php
$isEdit = $court !== null;
$action = $isEdit ? url('/admin/courts/' . (int) $court['id']) : url('/admin/courts');
?>

<div class="row mb-2" style="gap:10px">
  <a href="<?= url('/admin/courts') ?>" class="icon-btn" style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <h1 style="margin:0"><?= $isEdit ? 'Edit court' : 'Add a court' ?></h1>
</div>

<form method="post" action="<?= $action ?>" data-guard>
  <?= csrf_field() ?>

  <div class="card mb-2" style="max-width:620px">
    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="court_name">Court name</label>
        <input type="text" id="court_name" name="court_name" class="input <?= has_error('court_name') ? 'is-invalid' : '' ?>"
               value="<?= old('court_name', $court['court_name'] ?? '') ?>" required maxlength="60">
        <?= error_for('court_name') ?>
      </div>

      <div class="field">
        <label for="court_code">Code</label>
        <input type="text" id="court_code" name="court_code" class="input <?= has_error('court_code') ? 'is-invalid' : '' ?>"
               value="<?= old('court_code', $court['court_code'] ?? '') ?>" required maxlength="20" placeholder="CT-01">
        <?= error_for('court_code') ?>
      </div>
    </div>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="type">Court type</label>
        <select id="type" name="type" class="select" required>
          <?php foreach (['outdoor','indoor','covered'] as $t): ?>
            <option value="<?= $t ?>" <?= ($court['type'] ?? 'outdoor') === $t ? 'selected' : '' ?>><?= e(label($t)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="surface">Surface <span class="label-hint">(optional)</span></label>
        <input type="text" id="surface" name="surface" class="input" maxlength="40"
               value="<?= old('surface', $court['surface'] ?? '') ?>" placeholder="Acrylic, concrete…">
      </div>
    </div>

    <div class="grid grid-3" style="gap:0 12px">
      <div class="field">
        <label for="hourly_rate">Hourly rate (₱)</label>
        <input type="number" id="hourly_rate" name="hourly_rate" class="input" step="0.01" min="0" required
               value="<?= old('hourly_rate', $court['hourly_rate'] ?? '300.00') ?>">
        <?= error_for('hourly_rate') ?>
      </div>

      <div class="field">
        <label for="capacity">Player capacity</label>
        <input type="number" id="capacity" name="capacity" class="input" min="2" max="20" required
               value="<?= old('capacity', $court['capacity'] ?? 4) ?>">
      </div>

      <div class="field">
        <label for="sort_order">Display order</label>
        <input type="number" id="sort_order" name="sort_order" class="input" min="0" max="99"
               value="<?= old('sort_order', $court['sort_order'] ?? 0) ?>">
      </div>
    </div>

    <div class="field">
      <label for="status">Status</label>
      <select id="status" name="status" class="select" required>
        <option value="available"   <?= ($court['status'] ?? 'available') === 'available' ? 'selected' : '' ?>>Available for booking</option>
        <option value="maintenance" <?= ($court['status'] ?? '') === 'maintenance' ? 'selected' : '' ?>>Under maintenance</option>
        <option value="inactive"    <?= ($court['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>
      <p class="field-help">Only "available" courts appear to customers.</p>
    </div>

    <div class="field" style="margin-bottom:0">
      <label for="description">Description <span class="label-hint">(optional)</span></label>
      <textarea id="description" name="description" class="textarea" rows="3"
                maxlength="1000"><?= old('description', $court['description'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="btn-group">
    <button type="submit" class="btn btn-primary" data-loading="Saving…"><?= $isEdit ? 'Save changes' : 'Add court' ?></button>
    <a href="<?= url('/admin/courts') ?>" class="btn btn-ghost">Cancel</a>
  </div>
</form>
