# -*- coding: utf-8 -*-
"""
Turns the raw, server-rendered HTML snapshots in docs/_raw/ into a
self-contained static site in docs/, suitable for GitHub Pages.

What it does to every page:
  1. Rewrites every href/action/src that points at a route this snapshot
     set covers, to the matching flat .html filename (no leading slash, so
     it works under a GitHub Pages project subpath).
  2. Routes NOT captured (e.g. a reservation row for an id we didn't snapshot)
     go to preview-notice.html instead of a dead link.
  3. Blocks every <form method="post"> from actually submitting (there is no
     server), replacing the action with a friendly explanation instead.
  4. Adds a fixed banner at the top of every page identifying it as a static
     preview, with one-click links into the customer and admin views and a
     link back to the source repository.
  5. Copies public/assets/ (CSS, JS, SVGs) in as-is.

This is deliberately a thin, regex-based rewriter, not an HTML parser: the
input is server-rendered markup we control, not arbitrary HTML, so this is
safe and keeps the whole thing dependency-free.
"""
import os
import re
import shutil

RAW = os.path.join(os.path.dirname(__file__), '_raw')
OUT = os.path.join(os.path.dirname(__file__), '..', '..', 'docs')
PROJECT_ROOT = os.path.join(os.path.dirname(__file__), '..', '..')

# path (as it appears in the rendered HTML, i.e. with base_url = '') -> static filename
ROUTES = {
    '/':                                    'index.html',
    '/about':                               'about.html',
    '/availability':                        'availability.html',
    '/availability?type=function_hall':     'availability-hall.html',
    '/availability?type=court':             'availability.html',
    '/events':                              'events.html',
    '/events/1':                            'event-1.html',
    '/login':                               'login.html',
    '/register':                            'register.html',
    '/forgot-password':                     'forgot-password.html',

    '/dashboard':                           'dashboard.html',
    '/profile':                             'profile.html',
    '/reserve':                             'reserve.html',
    '/reserve/court':                       'reserve-court.html',
    '/reserve/hall':                        'reserve-hall.html',
    '/reservations':                        'reservations.html',
    '/reservations?filter=upcoming':        'reservations.html',
    '/reservations?filter=past':            'reservations-past.html',
    '/reservations?filter=cancelled':       'reservations.html',
    '/reservations?filter=all':             'reservations.html',
    '/reservations/MDC-2026-000022':        'reservation-detail.html',
    '/matching':                            'matching.html',
    '/matching/results':                    'matching-results.html',
    '/notifications':                       'notifications.html',
    '/logout':                              'index.html',

    '/admin':                               'admin-dashboard.html',
    '/admin/reservations':                  'admin-reservations.html',
    '/admin/reservations/10':               'admin-reservation-pending.html',
    '/admin/reservations/24':               'admin-reservation-approved.html',
    '/admin/reservations/create':           'admin-reservation-create.html',
    '/admin/schedules':                     'admin-schedules.html',
    '/admin/players':                       'admin-players.html',
    '/admin/players/1':                     'admin-player-online.html',
    '/admin/players/9':                     'admin-player-walkin.html',
    '/admin/players/walk-in':               'admin-walkin.html',
    '/admin/matching':                      'admin-matching.html',
    '/admin/matching/1':                    'admin-matching-generated.html',
    '/admin/matching/4':                    'admin-matching-open.html',
    '/admin/matching/create':               'admin-matching-create.html',
    '/admin/events':                        'admin-events.html',
    '/admin/events/1':                      'admin-event-detail.html',
    '/admin/events/create':                 'admin-event-create.html',
    '/admin/payments':                      'admin-payments.html',
    '/admin/reports':                       'admin-reports.html',
    '/admin/reports/reservation':           'admin-report-reservation.html',
    '/admin/reports/utilisation':           'admin-report-utilisation.html',
    '/admin/reports/event':                 'admin-report-event.html',
    '/admin/reports/payment':               'admin-report-payment.html',
    '/admin/reports/player':                'admin-report-player.html',
    '/admin/courts':                        'admin-courts.html',
    '/admin/courts/1/edit':                 'admin-court-edit.html',
    '/admin/halls':                         'admin-halls.html',
    '/admin/users':                         'admin-users.html',
    '/admin/settings':                      'admin-settings.html',
    '/admin/logs':                          'admin-logs.html',
    '/admin/notifications':                 'admin-notifications.html',
}

# Fallback for admin-only prefixes that have at least one page in the set,
# so an uncaptured id (e.g. a different reservation row) still lands
# somewhere sensible rather than on the generic notice.
PREFIX_FALLBACK = [
    ('/admin/reservations/', 'admin-reservation-approved.html'),
    ('/admin/players/',      'admin-player-online.html'),
    ('/admin/matching/',     'admin-matching-generated.html'),
    ('/admin/events/',       'admin-event-detail.html'),
    ('/reservations/',       'reservation-detail.html'),
    ('/events/',             'event-1.html'),
]

NOTICE = 'preview-notice.html'

BANNER = """
<div id="preview-banner" style="position:sticky;top:0;z-index:200;background:#0f172a;color:#fff;
     font-size:.82rem;padding:9px 14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;
     border-bottom:2px solid #a3e635;font-family:-apple-system,'Segoe UI',sans-serif">
  <strong style="color:#a3e635">STATIC PREVIEW</strong>
  <span style="opacity:.85">Real screens, sample data &mdash; no live database. Forms are disabled.</span>
  <span style="margin-left:auto;display:flex;gap:12px;flex-wrap:wrap">
    <a href="dashboard.html" style="color:#fff;text-decoration:underline">View as customer</a>
    <a href="admin-dashboard.html" style="color:#fff;text-decoration:underline">View as admin</a>
    <a href="https://github.com/Angelordsz/MANGO-CAFE-PICKLEBALL" style="color:#fff;text-decoration:underline">Source code</a>
  </span>
</div>
"""

BLOCK_SCRIPT = """
<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form').forEach(function (form) {
      var method = (form.getAttribute('method') || 'get').toLowerCase();
      if (method === 'post') {
        form.addEventListener('submit', function (e) {
          e.preventDefault();
          alert('This is a static preview of the interface.\\n\\nSubmitting forms, approving reservations and generating matches all require the live PHP + MySQL application. See the README in the source repository for setup instructions.');
        });
      }
    });
  });
</script>
"""


def map_path(path):
    """Resolve a route path to a static filename, or the fallback notice."""
    if path in ROUTES:
        return ROUTES[path]
    # Strip a query string and try again (covers ?date=... variants etc.)
    base = path.split('?', 1)[0]
    if base in ROUTES:
        return ROUTES[base]
    for prefix, target in PREFIX_FALLBACK:
        if path.startswith(prefix) or base.startswith(prefix):
            return target
    return None


def rewrite_attr(match, attr_name):
    quote = match.group(1)
    value = match.group(2)

    # Leave alone: external links, in-page anchors, mailto, javascript:, and
    # asset paths (handled separately below).
    if value.startswith(('http://', 'https://', '#', 'mailto:', 'javascript:')):
        return match.group(0)
    if value.startswith('/assets/') or value.startswith('/uploads/'):
        return '%s=%s%s%s' % (attr_name, quote, value.lstrip('/'), quote)
    if not value.startswith('/'):
        return match.group(0)

    target = map_path(value)
    if target is None:
        target = NOTICE
    return '%s=%s%s%s' % (attr_name, quote, target, quote)


ATTR_RE = re.compile(r'\b(href|action|src)=("|\')((?:(?!\2).)*)\2')


def rewrite_links(html):
    def _sub(m):
        attr = m.group(1)
        quote = m.group(2)
        value = m.group(3)
        fake = re.match(r'^()$', '')  # placeholder, unused
        # Reuse rewrite_attr's logic via a tiny shim:
        class Shim:
            def group(self, i):
                return (attr, quote, value)[i]
        return rewrite_attr(Shim(), attr)
    return ATTR_RE.sub(_sub, html)


def build_page(raw_name, out_name):
    with open(os.path.join(RAW, raw_name), encoding='utf-8') as f:
        html = f.read()

    html = rewrite_links(html)

    # Inject the banner right after <body ...>.
    html = re.sub(r'(<body[^>]*>)', r'\1' + BANNER, html, count=1)

    # Inject the form-blocking script right before </body>.
    html = html.replace('</body>', BLOCK_SCRIPT + '</body>')

    with open(os.path.join(OUT, out_name), 'w', encoding='utf-8') as f:
        f.write(html)


def main():
    # 1. Every mapped page.
    seen_raw = set()
    for path, filename in ROUTES.items():
        raw_candidates = {
            '/':                                'index',
            '/availability?type=function_hall': 'availability-hall',
            '/availability?type=court':          'availability',
            '/reservations?filter=upcoming':     'reservations',
            '/reservations?filter=past':         'reservations-past',
            '/reservations?filter=cancelled':    'reservations',
            '/reservations?filter=all':          'reservations',
            '/reservations/MDC-2026-000022':     'reservation-detail',
            '/logout':                           'index',
        }
        raw_slug = raw_candidates.get(path, filename[:-5])
        raw_file = raw_slug + '.html'
        if raw_file in seen_raw:
            continue
        raw_path = os.path.join(RAW, raw_file)
        if not os.path.isfile(raw_path):
            print('  ! no snapshot for', path, '(wanted', raw_file, ')')
            continue
        build_page(raw_file, filename)
        seen_raw.add(raw_file)
        print('  built', filename)

    # 2. The fallback notice page (hand-written, not from a snapshot).
    notice_html = """<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Not in this preview &middot; Mango Drive Pickleball</title>
<link rel="stylesheet" href="assets/css/app.css">
</head><body>""" + BANNER + """
<main class="page"><div class="container" style="max-width:520px;margin:60px auto;text-align:center">
  <span class="empty-icon" style="margin:0 auto 16px"></span>
  <h1>This exact record isn't in the preview</h1>
  <p class="muted">
    This static demo snapshots a representative sample of pages from the real application
    &mdash; not every row in every list links to its own captured detail page.
  </p>
  <p class="muted">Run the live application locally to browse every record: see the
     <a href="https://github.com/Angelordsz/MANGO-CAFE-PICKLEBALL#quick-start-xampp">setup guide</a>.</p>
  <p><a href="index.html" class="btn btn-primary">Back to the home page</a></p>
</div></main>
</body></html>"""
    with open(os.path.join(OUT, NOTICE), 'w', encoding='utf-8') as f:
        f.write(notice_html)
    print('  built', NOTICE)

    # 3. Custom 404 (GitHub Pages serves docs/404.html automatically).
    not_found = """<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Page not found &middot; Mango Drive Pickleball</title>
<link rel="stylesheet" href="assets/css/app.css"></head><body>""" + BANNER + """
<main class="page"><div class="container" style="max-width:480px;margin:60px auto;text-align:center">
  <h1>404</h1>
  <p class="muted">That page is not part of this static preview.</p>
  <p><a href="index.html" class="btn btn-primary">Back to the home page</a></p>
</div></main></body></html>"""
    with open(os.path.join(OUT, '404.html'), 'w', encoding='utf-8') as f:
        f.write(not_found)
    print('  built 404.html')

    # 4. Assets.
    src_assets = os.path.join(PROJECT_ROOT, 'public', 'assets')
    dst_assets = os.path.join(OUT, 'assets')
    if os.path.isdir(dst_assets):
        shutil.rmtree(dst_assets)
    shutil.copytree(src_assets, dst_assets)
    print('  copied assets/')

    # 5. .nojekyll so GitHub Pages serves the files as-is.
    open(os.path.join(OUT, '.nojekyll'), 'w').close()
    print('  wrote .nojekyll')


if __name__ == '__main__':
    main()
