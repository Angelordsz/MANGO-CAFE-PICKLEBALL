# Static Preview Builder

Written for: whoever needs to regenerate the click-through demo published at
`docs/` and served by GitHub Pages, after the interface changes.

---

## What this is

`docs/*.html` is **not hand-written** — it is the real application's own rendered
output, fetched from a running instance and lightly rewritten so it works with
no PHP and no database behind it (which is all GitHub Pages can serve). It exists
so a client can open one link and click through the real screens with real
sample data, without anyone installing PHP or MySQL.

It is a look-alike, not the application. Every `<form method="post">` is
intercepted client-side and shows an explanation instead of submitting — there
is nothing to submit to. See the banner at the top of every preview page.

## Regenerating it after a UI change

1. Start the real app locally and seed it:
   ```bat
   php database\seed.php --force
   cd public && php -S 127.0.0.1:8123
   ```
2. From this folder, fetch fresh snapshots (edit the IDs in `fetch.sh` first if
   your reseed produced different reservation/player/matching IDs than the
   ones currently hard-coded there):
   ```bat
   cd scripts\static-preview
   bash fetch.sh
   ```
3. Rebuild the static site:
   ```bat
   python build.py
   ```
   This regenerates every file in `docs/*.html` and `docs/assets/`, rewriting
   every internal link to a flat filename and re-injecting the preview banner
   and the form-blocking script. It does not touch `docs/*.md` (the project
   documentation living alongside the preview).
4. Commit and push `docs/` — GitHub Pages redeploys automatically within a
   minute or two.

## Adding a new page to the preview

Edit two places in `build.py`:

- `ROUTES`, mapping the route path (exactly as it appears in the rendered
  HTML — i.e. with no `base_url` prefix) to the static filename you want.
- If it is a detail page under a prefix you already cover (e.g. another
  `/admin/events/{id}`), you do not need to touch `PREFIX_FALLBACK` — only
  add there if you are introducing a **new** prefix.

Then add the matching `get "<jar>" "<path>" "<slug>"` line to `fetch.sh`, using
the same slug as the map's raw-file lookup expects (see the `raw_candidates`
dict in `build.py` for the handful of paths whose slug does not simply match
the filename).

## Files

| File | Purpose |
|------|---------|
| `fetch.sh` | Logs in as a customer and as an admin, fetches every route in the sample set, saves raw HTML to `_raw/` (git-ignored, scratch) |
| `build.py` | Rewrites the raw HTML into `docs/`: relative links, blocked forms, the preview banner, plus a 404 page and the "not in this preview" fallback |

Neither script has external dependencies beyond `curl` and a plain Python 3
install.
