<?php
$isEdit = $hall !== null;
$action = $isEdit ? url('/admin/halls/' . (int) $hall['id']) : url('/admin/halls');
?>

<div class="row mb-2" style="gap:10px">
  <a href="<?= url('/admin/halls') ?>" class="icon-btn" style="background:var(--bg-sunken);color:var(--text)" aria-label="Back">
    <?= icon('arrow-left', 18) ?>
  </a>
  <h1 style="margin:0"><?= $isEdit ? 'Edit function hall' : 'Add a function hall' ?></h1>
</div>

<form method="post" action="<?= $action ?>" data-guard>
  <?= csrf_field() ?>

  <div class="card mb-2" style="max-width:620px">
    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="hall_name">Hall name</label>
        <input type="text" id="hall_name" name="hall_name" class="input <?= has_error('hall_name') ? 'is-invalid' : '' ?>"
               value="<?= old('hall_name', $hall['hall_name'] ?? '') ?>" required maxlength="60">
        <?= error_for('hall_name') ?>
      </div>

      <div class="field">
        <label for="hall_code">Code</label>
        <input type="text" id="hall_code" name="hall_code" class="input <?= has_error('hall_code') ? 'is-invalid' : '' ?>"
               value="<?= old('hall_code', $hall['hall_code'] ?? '') ?>" required maxlength="20" placeholder="FH-01">
        <?= error_for('hall_code') ?>
      </div>
    </div>

    <div class="grid grid-3" style="gap:0 12px">
      <div class="field">
        <label for="rental_fee">Rate per hour (₱)</label>
        <input type="number" id="rental_fee" name="rental_fee" class="input" step="0.01" min="0" required
               value="<?= old('rental_fee', $hall['rental_fee'] ?? '1500.00') ?>">
        <?= error_for('rental_fee') ?>
      </div>

      <div class="field">
        <label for="capacity">Guest capacity</label>
        <input type="number" id="capacity" name="capacity" class="input" min="1" max="2000" required
               value="<?= old('capacity', $hall['capacity'] ?? 50) ?>">
      </div>

      <div class="field">
        <label for="min_hours">Minimum hours</label>
        <input type="number" id="min_hours" name="min_hours" class="input" min="1" max="12" required
               value="<?= old('min_hours', $hall['min_hours'] ?? 2) ?>">
      </div>
    </div>

    <div class="field">
      <label for="amenities">Amenities <span class="label-hint">(comma separated)</span></label>
      <input type="text" id="amenities" name="amenities" class="input" maxlength="500"
             value="<?= old('amenities', $hall['amenities'] ?? '') ?>"
             placeholder="Projector, sound system, aircon, catering">
    </div>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status" class="select" required>
          <option value="available"   <?= ($hall['status'] ?? 'available') === 'available' ? 'selected' : '' ?>>Available for booking</option>
          <option value="maintenance" <?= ($hall['status'] ?? '') === 'maintenance' ? 'selected' : '' ?>>Under maintenance</option>
          <option value="inactive"    <?= ($hall['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>

      <div class="field">
        <label for="sort_order">Display order</label>
        <input type="number" id="sort_order" name="sort_order" class="input" min="0" max="99"
               value="<?= old('sort_order', $hall['sort_order'] ?? 0) ?>">
      </div>
    </div>

    <div class="field" style="margin-bottom:0">
      <label for="description">Description <span class="label-hint">(optional)</span></label>
      <textarea id="description" name="description" class="textarea" rows="3"
                maxlength="1000"><?= old('description', $hall['description'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="btn-group">
    <button type="submit" class="btn btn-primary" data-loading="Saving…"><?= $isEdit ? 'Save changes' : 'Add hall' ?></button>
    <a href="<?= url('/admin/halls') ?>" class="btn btn-ghost">Cancel</a>
  </div>
</form>
