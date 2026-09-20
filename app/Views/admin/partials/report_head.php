<?php
/**
 * Shared report header: title, date range filter, export + print buttons.
 * Expects $reportTitle, $reportPath, $from, $to, and optionally $extra (HTML
 * string of additional filter fields) and $query (array of extra query args).
 */
$query = $query ?? [];
$exportQuery = array_merge($query, ['from' => $from, 'to' => $to, 'export' => 'csv']);
?>
<div class="row-between mb-2">
  <div>
    <h1 style="margin:0"><?= e($reportTitle) ?></h1>
    <p class="small muted" style="margin:0">
      Mango Drive Café · <?= e(fdate($from)) ?> to <?= e(fdate($to)) ?>
      · generated <?= e(date('M j, Y g:i A')) ?>
    </p>
  </div>
  <div class="btn-group no-print">
    <a href="<?= url('/admin/reports') ?>" class="btn btn-ghost btn-sm">
      <?= icon('arrow-left', 15) ?> Reports
    </a>
    <a href="<?= url($reportPath . '?' . http_build_query($exportQuery)) ?>" class="btn btn-outline btn-sm">
      <?= icon('download', 15) ?> CSV
    </a>
    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
      <?= icon('print', 15) ?> Print
    </button>
  </div>
</div>

<form method="get" action="<?= url($reportPath) ?>" class="filters no-print" data-auto-filter>
  <div class="field">
    <label for="from">From</label>
    <input type="date" id="from" name="from" class="input" value="<?= e($from) ?>">
  </div>
  <div class="field">
    <label for="to">To</label>
    <input type="date" id="to" name="to" class="input" value="<?= e($to) ?>">
  </div>
  <?= $extra ?? '' ?>
  <div class="field" style="flex:0 0 auto">
    <button type="submit" class="btn btn-outline"><?= icon('filter', 15) ?> Apply</button>
  </div>
</form>
