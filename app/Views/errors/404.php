<?php
// Helpers may not be loaded if the failure happened very early in bootstrap,
// so fall back to root-relative paths.
$cssHref = function_exists('asset') ? asset('css/app.css') : '/assets/css/app.css';
$homeUrl = function_exists('url') ? url('/') : '/';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>404 — Page not found</title>
<link rel="stylesheet" href="<?= htmlspecialchars($cssHref, ENT_QUOTES) ?>">
<script>
  (function () {
    try {
      var saved = localStorage.getItem('mdc-theme');
      var dark  = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
      document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    } catch (e) {}
  })();
</script>
<style>
  body { display:grid; place-items:center; min-height:100vh; padding:24px; text-align:center; }
  .code { font-size:4.5rem; font-weight:800; letter-spacing:-.04em; color:var(--lime-500); line-height:1; margin:0; }
</style>
</head>
<body>
  <main style="max-width:430px">
    <p class="code">404</p>
    <h1>Page not found</h1>
    <p class="muted mb-3">The page you are looking for does not exist, or has moved.</p>
    <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES) ?>" class="btn btn-primary">Back to the home page</a>
  </main>
</body>
</html>
