<?php
/**
 * Back-office layout: sidebar + content. Used by every Admin\* controller.
 */
use App\Core\Auth;
use App\Core\Database;

$user      = Auth::user();
$isAdmin   = Auth::isAdmin();
$pendingRes = (int) Database::scalar(
    "SELECT COUNT(*) FROM reservations WHERE status = 'pending'", [], 0
);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<title><?= e($title ?? 'Back office') ?></title>
<link rel="icon" href="<?= asset('img/favicon.svg') ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<script>
  (function () {
    try {
      var saved = localStorage.getItem('mdc-theme');
      var dark  = saved ? saved === 'dark'
                        : window.matchMedia('(prefers-color-scheme: dark)').matches;
      document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    } catch (e) {
      document.documentElement.setAttribute('data-theme', 'light');
    }
  })();
</script>
</head>
<body>

<a class="skip-link" href="#main">Skip to main content</a>

<header class="topbar">
  <div class="container topbar-inner">
    <button type="button" class="icon-btn no-print" data-sidebar-toggle aria-expanded="false" aria-label="Toggle menu"
            style="display:grid">
      <?= icon('menu', 19) ?>
    </button>

    <a href="<?= url('/admin') ?>" class="brand">
      <span class="brand-mark"><?= icon('paddle', 20) ?></span>
      <span class="brand-text">
        Mango Drive
        <span><?= $isAdmin ? 'Administrator' : 'Staff' ?></span>
      </span>
    </a>

    <div class="topbar-actions">
      <button type="button" class="icon-btn no-print" data-theme-toggle aria-label="Switch colour theme">
        <?= icon('sun', 18) ?>
      </button>

      <div class="menu" data-menu>
        <button type="button" class="avatar" data-menu-trigger aria-haspopup="true" aria-label="Account menu">
          <?= e(strtoupper(substr($user['username'], 0, 2))) ?>
        </button>
        <div class="menu-panel" data-menu-panel>
          <div style="padding:9px 12px">
            <strong style="display:block"><?= e($user['username']) ?></strong>
            <span class="small muted"><?= e(label($user['role'])) ?></span>
          </div>
          <div class="menu-divider"></div>
          <a href="<?= url('/') ?>"><?= icon('home', 17) ?> View public site</a>
          <div class="menu-divider"></div>
          <form method="post" action="<?= url('/logout') ?>">
            <?= csrf_field() ?>
            <button type="submit"><?= icon('logout', 17) ?> Log out</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</header>

<div class="container page">
  <div class="admin-shell">

    <nav class="sidebar no-print" aria-label="Back office">
      <div class="sidebar-group">
        <p class="sidebar-title">Overview</p>
        <a href="<?= url('/admin') ?>" class="<?= nav_exact('/admin') ?>">
          <?= icon('chart', 17) ?> Dashboard
        </a>
      </div>

      <div class="sidebar-group">
        <p class="sidebar-title">Reservations</p>
        <a href="<?= url('/admin/reservations') ?>" class="<?= nav_active('/admin/reservations') ?>">
          <?= icon('ticket', 17) ?> Reservations
          <?php if ($pendingRes): ?><span class="count"><?= $pendingRes ?></span><?php endif; ?>
        </a>
        <a href="<?= url('/admin/schedules') ?>" class="<?= nav_active('/admin/schedules') ?>">
          <?= icon('calendar', 17) ?> Facility schedules
        </a>
      </div>

      <div class="sidebar-group">
        <p class="sidebar-title">Players &amp; play</p>
        <a href="<?= url('/admin/players') ?>" class="<?= nav_active('/admin/players') ?>">
          <?= icon('users', 17) ?> Player records
        </a>
        <a href="<?= url('/admin/matching') ?>" class="<?= nav_active('/admin/matching') ?>">
          <?= icon('shuffle', 17) ?> Player matching
        </a>
        <a href="<?= url('/admin/events') ?>" class="<?= nav_active('/admin/events') ?>">
          <?= icon('trophy', 17) ?> Events
        </a>
      </div>

      <div class="sidebar-group">
        <p class="sidebar-title">Money</p>
        <a href="<?= url('/admin/payments') ?>" class="<?= nav_active('/admin/payments') ?>">
          <?= icon('money', 17) ?> Payments
        </a>
        <a href="<?= url('/admin/reports') ?>" class="<?= nav_active('/admin/reports') ?>">
          <?= icon('clipboard', 17) ?> Reports
        </a>
      </div>

      <?php if ($isAdmin): ?>
        <div class="sidebar-group">
          <p class="sidebar-title">Facilities</p>
          <a href="<?= url('/admin/courts') ?>" class="<?= nav_active('/admin/courts') ?>">
            <?= icon('court', 17) ?> Courts
          </a>
          <a href="<?= url('/admin/halls') ?>" class="<?= nav_active('/admin/halls') ?>">
            <?= icon('hall', 17) ?> Function halls
          </a>
        </div>

        <div class="sidebar-group">
          <p class="sidebar-title">Administration</p>
          <a href="<?= url('/admin/users') ?>" class="<?= nav_active('/admin/users') ?>">
            <?= icon('user', 17) ?> User accounts
          </a>
          <a href="<?= url('/admin/notifications') ?>" class="<?= nav_active('/admin/notifications') ?>">
            <?= icon('bell', 17) ?> Send notifications
          </a>
          <a href="<?= url('/admin/logs') ?>" class="<?= nav_active('/admin/logs') ?>">
            <?= icon('list', 17) ?> System logs
          </a>
          <a href="<?= url('/admin/settings') ?>" class="<?= nav_active('/admin/settings') ?>">
            <?= icon('settings', 17) ?> Settings
          </a>
        </div>
      <?php else: ?>
        <div class="sidebar-group">
          <p class="sidebar-title">Administration</p>
          <a href="<?= url('/admin/notifications') ?>" class="<?= nav_active('/admin/notifications') ?>">
            <?= icon('bell', 17) ?> Send notifications
          </a>
        </div>
      <?php endif; ?>
    </nav>

    <main id="main">
      <?php if (!empty($flashes)): ?>
        <div class="mb-2 no-print">
          <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> mb-1" role="status" data-flash>
              <?= icon($flash['type'] === 'success' ? 'check' : ($flash['type'] === 'error' ? 'warning' : 'info'), 18) ?>
              <span><?= e($flash['message']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?= $content ?>
    </main>
  </div>
</div>

<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
