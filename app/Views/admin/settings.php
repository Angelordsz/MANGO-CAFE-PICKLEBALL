<?php
$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$hoursByDay = [];
foreach ($hours as $row) {
    $hoursByDay[(int) $row['day_of_week']] = $row;
}
?>

<h1 class="mb-1">Settings</h1>
<p class="muted mb-3">Café details, operating hours and booking rules.</p>

<form method="post" action="<?= url('/admin/settings') ?>" data-guard>
  <?= csrf_field() ?>

  <!-- Café details -->
  <div class="card mb-3">
    <h2 class="mb-2">Café details</h2>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="cafe_name">Business name</label>
        <input type="text" id="cafe_name" name="settings[cafe_name]" class="input"
               value="<?= e($settings['cafe_name'] ?? 'Mango Drive Cafe') ?>" maxlength="120">
      </div>
      <div class="field">
        <label for="cafe_phone">Contact number</label>
        <input type="text" id="cafe_phone" name="settings[cafe_phone]" class="input"
               value="<?= e($settings['cafe_phone'] ?? '') ?>" maxlength="40">
      </div>
    </div>

    <div class="field">
      <label for="cafe_address">Address</label>
      <input type="text" id="cafe_address" name="settings[cafe_address]" class="input"
             value="<?= e($settings['cafe_address'] ?? '') ?>" maxlength="255">
    </div>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="cafe_email">Email</label>
        <input type="email" id="cafe_email" name="settings[cafe_email]" class="input"
               value="<?= e($settings['cafe_email'] ?? '') ?>" maxlength="190">
      </div>
      <div class="field">
        <label for="cafe_facebook">Facebook page</label>
        <input type="text" id="cafe_facebook" name="settings[cafe_facebook]" class="input"
               value="<?= e($settings['cafe_facebook'] ?? '') ?>" maxlength="190">
      </div>
    </div>
  </div>

  <!-- Operating hours -->
  <div class="card mb-3">
    <h2 class="mb-1">Operating hours</h2>
    <p class="small muted mb-2">
      These decide which time slots the schedule generator creates. Changing them does not
      alter slots that already exist — regenerate the schedule afterwards.
    </p>

    <div class="table-wrap">
      <table class="table table-compact">
        <thead>
          <tr><th>Day</th><th>Opens</th><th>Closes</th><th>Closed</th></tr>
        </thead>
        <tbody>
          <?php foreach ($dayNames as $dow => $dayName): ?>
            <?php $row = $hoursByDay[$dow] ?? ['open_time' => '06:00:00', 'close_time' => '22:00:00', 'is_closed' => 0]; ?>
            <tr>
              <td><strong class="small"><?= $dayName ?></strong></td>
              <td>
                <input type="time" name="hours[<?= $dow ?>][open_time]" class="input"
                       style="padding:6px 8px;font-size:.84rem;max-width:130px"
                       value="<?= e(substr($row['open_time'], 0, 5)) ?>" step="1800">
              </td>
              <td>
                <input type="time" name="hours[<?= $dow ?>][close_time]" class="input"
                       style="padding:6px 8px;font-size:.84rem;max-width:130px"
                       value="<?= e(substr($row['close_time'], 0, 5)) ?>" step="1800">
              </td>
              <td>
                <label class="check" style="margin:0">
                  <input type="checkbox" name="hours[<?= $dow ?>][is_closed]" value="1"
                         <?= (int) $row['is_closed'] === 1 ? 'checked' : '' ?>>
                  <span class="small">Closed</span>
                </label>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Booking rules -->
  <div class="card mb-3">
    <h2 class="mb-1">Booking rules</h2>
    <p class="small muted mb-2">
      Shown to customers and enforced when they book. The authoritative values live in
      <code>config/config.php</code>; these are the copies displayed in the interface.
    </p>

    <div class="grid grid-2" style="gap:0 12px">
      <div class="field">
        <label for="cancel_cutoff_hours">Cancellation cut-off (hours)</label>
        <input type="number" id="cancel_cutoff_hours" name="settings[cancel_cutoff_hours]" class="input"
               min="0" max="168" value="<?= e($settings['cancel_cutoff_hours'] ?? '12') ?>">
        <p class="field-help">Customers cannot cancel online inside this window.</p>
      </div>

      <div class="field">
        <label for="max_advance_days">Book up to (days ahead)</label>
        <input type="number" id="max_advance_days" name="settings[max_advance_days]" class="input"
               min="1" max="365" value="<?= e($settings['max_advance_days'] ?? '30') ?>">
      </div>
    </div>

    <div class="field">
      <label for="booking_policy">Reservation policy text</label>
      <textarea id="booking_policy" name="settings[booking_policy]" class="textarea" rows="3"
                maxlength="2000"><?= e($settings['booking_policy'] ?? '') ?></textarea>
      <p class="field-help">Shown on the booking confirmation screen.</p>
    </div>
  </div>

  <!-- Skill levels (read-only reference) -->
  <div class="card mb-3">
    <h2 class="mb-1">Skill levels</h2>
    <p class="small muted mb-2">
      The controlled vocabulary the matching module buckets players into. Edit these directly
      in the <code>skill_levels</code> table if the café's grading changes.
    </p>

    <div class="table-wrap">
      <table class="table table-compact">
        <thead>
          <tr><th>Level</th><th>Code</th><th>Rating range</th><th>Description</th><th>Active</th></tr>
        </thead>
        <tbody>
          <?php foreach ($levels as $level): ?>
            <tr>
              <td><strong class="small"><?= e($level['level_name']) ?></strong></td>
              <td><code class="tiny"><?= e($level['level_code']) ?></code></td>
              <td class="small">
                <?= $level['rating_low'] !== null
                      ? number_format((float) $level['rating_low'], 1) . ' – ' . number_format((float) $level['rating_high'], 1)
                      : '—' ?>
              </td>
              <td class="small muted"><?= e($level['description']) ?></td>
              <td>
                <span class="badge badge-<?= $level['is_active'] ? 'ok' : 'muted' ?>">
                  <?= $level['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <button type="submit" class="btn btn-primary" data-loading="Saving…">Save settings</button>
</form>
