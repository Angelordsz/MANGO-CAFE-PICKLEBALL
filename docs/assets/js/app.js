/* =========================================================================
   Mango Drive Cafe Pickleball -- front-end behaviour
   Vanilla JS, no dependencies. Progressive enhancement only: every page
   still works with JavaScript disabled.
   ========================================================================= */
(function () {
  'use strict';

  /* ---- Theme (light / dark) --------------------------------------------
     Applied in <head> by an inline script to avoid a flash of wrong theme;
     this only handles the toggle button. */
  function initTheme() {
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var root = document.documentElement;
        var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', next);
        try { localStorage.setItem('mdc-theme', next); } catch (e) { /* private mode */ }
        btn.setAttribute('aria-label', next === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
      });
    });
  }

  /* ---- Dropdown menus --------------------------------------------------- */
  function initMenus() {
    var open = null;

    function close() {
      if (open) { open.panel.hidden = true; open.trigger.setAttribute('aria-expanded', 'false'); open = null; }
    }

    document.querySelectorAll('[data-menu]').forEach(function (menu) {
      var trigger = menu.querySelector('[data-menu-trigger]');
      var panel   = menu.querySelector('[data-menu-panel]');
      if (!trigger || !panel) return;

      panel.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');

      trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        var wasOpen = open && open.panel === panel;
        close();
        if (!wasOpen) {
          panel.hidden = false;
          trigger.setAttribute('aria-expanded', 'true');
          open = { panel: panel, trigger: trigger };
        }
      });
    });

    document.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  }

  /* ---- Confirm before destructive submits ------------------------------- */
  function initConfirms() {
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        if (!window.confirm(el.getAttribute('data-confirm'))) {
          e.preventDefault();
          e.stopPropagation();
        }
      });
    });
  }

  /* ---- Auto-dismiss flash messages -------------------------------------- */
  function initFlashes() {
    document.querySelectorAll('[data-flash]').forEach(function (el) {
      setTimeout(function () {
        el.style.transition = 'opacity .4s, transform .4s';
        el.style.opacity = '0';
        el.style.transform = 'translateY(-6px)';
        setTimeout(function () { el.remove(); }, 400);
      }, 6000);
    });
  }

  /* ---- Booking slot picker ----------------------------------------------
     Slots must be contiguous: picking 9AM then 11AM is rejected, because a
     reservation is one unbroken block. Keeps the running total in sync. */
  function initSlotPicker() {
    var form = document.querySelector('[data-slot-form]');
    if (!form) return;

    var checkboxes = function () { return Array.prototype.slice.call(form.querySelectorAll('.slot input[type=checkbox]:not(:disabled)')); };
    var totalEl    = form.querySelector('[data-total]');
    var hoursEl    = form.querySelector('[data-hours]');
    var rangeEl    = form.querySelector('[data-range]');
    var submitBtn  = form.querySelector('[data-submit]');
    var countEl    = form.querySelector('[data-count]');

    function refresh() {
      var all      = checkboxes();
      var selected = all.filter(function (c) { return c.checked; });

      selected.sort(function (a, b) { return Number(a.dataset.index) - Number(b.dataset.index); });

      // Enforce contiguity: drop anything that breaks the run.
      for (var i = 1; i < selected.length; i++) {
        if (Number(selected[i].dataset.index) !== Number(selected[i - 1].dataset.index) + 1) {
          selected[i].checked = false;
          selected.splice(i, 1);
          i--;
        }
      }

      var total = 0;
      selected.forEach(function (c) { total += parseFloat(c.dataset.price || '0'); });

      all.forEach(function (c) {
        c.closest('.slot').classList.toggle('is-selected', c.checked);
      });

      if (totalEl) totalEl.textContent = '₱' + total.toFixed(2);
      if (hoursEl) hoursEl.textContent = selected.length + (selected.length === 1 ? ' hour' : ' hours');
      if (countEl) countEl.textContent = String(selected.length);

      if (rangeEl) {
        rangeEl.textContent = selected.length
          ? selected[0].dataset.start + ' – ' + selected[selected.length - 1].dataset.end
          : 'No time selected';
      }

      if (submitBtn) {
        submitBtn.disabled = selected.length === 0;
      }
    }

    form.addEventListener('change', function (e) {
      if (e.target.matches('.slot input[type=checkbox]')) refresh();
    });

    // Keyboard: space/enter on the label wrapper
    form.querySelectorAll('.slot').forEach(function (slot) {
      var input = slot.querySelector('input');
      if (!input || input.disabled) return;
      slot.setAttribute('tabindex', '0');
      slot.setAttribute('role', 'checkbox');
      slot.setAttribute('aria-checked', input.checked ? 'true' : 'false');
      slot.addEventListener('keydown', function (e) {
        if (e.key === ' ' || e.key === 'Enter') {
          e.preventDefault();
          input.checked = !input.checked;
          slot.setAttribute('aria-checked', input.checked ? 'true' : 'false');
          refresh();
        }
      });
    });

    refresh();
  }

  /* ---- Date strip: load slots for the chosen day ------------------------ */
  function initDayStrip() {
    var strip = document.querySelector('[data-daystrip]');
    if (!strip) return;

    strip.addEventListener('click', function (e) {
      var day = e.target.closest('.day');
      if (!day || day.classList.contains('disabled')) return;

      var url = new URL(window.location.href);
      url.searchParams.set('date', day.dataset.date);
      window.location.href = url.toString();
    });
  }

  /* ---- Player search (admin: add player to event / matching pool) -------- */
  function initPlayerSearch() {
    document.querySelectorAll('[data-player-search]').forEach(function (box) {
      var input   = box.querySelector('input[type=search]');
      var results = box.querySelector('[data-results]');
      var target  = box.querySelector('input[type=hidden]');
      if (!input || !results) return;

      var timer = null;

      input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();

        if (q.length < 2) { results.innerHTML = ''; results.hidden = true; return; }

        timer = setTimeout(function () {
          fetch(box.dataset.playerSearch + '?q=' + encodeURIComponent(q), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              results.innerHTML = '';
              if (!data.players || !data.players.length) {
                results.innerHTML = '<p class="muted small" style="padding:10px">No player found.</p>';
                results.hidden = false;
                return;
              }
              data.players.forEach(function (p) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-item';
                btn.style.cssText = 'width:100%;background:none;border:0;cursor:pointer;text-align:left';
                btn.innerHTML =
                  '<span class="avatar avatar-sm">' + escapeHtml(p.initials) + '</span>' +
                  '<span class="grow"><strong>' + escapeHtml(p.name) + '</strong>' +
                  '<span class="small muted" style="display:block">' + escapeHtml(p.meta) + '</span></span>';
                btn.addEventListener('click', function () {
                  if (target) target.value = p.id;
                  input.value = p.name;
                  results.hidden = true;
                });
                results.appendChild(btn);
              });
              results.hidden = false;
            })
            .catch(function () { results.hidden = true; });
        }, 250);
      });
    });
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---- Auto-submit filter forms ----------------------------------------- */
  function initAutoFilters() {
    document.querySelectorAll('[data-auto-filter] select, [data-auto-filter] input[type=date]').forEach(function (el) {
      el.addEventListener('change', function () { el.form.submit(); });
    });
  }

  /* ---- Prevent double submit -------------------------------------------- */
  function initSubmitGuard() {
    document.querySelectorAll('form[data-guard]').forEach(function (form) {
      form.addEventListener('submit', function () {
        var btn = form.querySelector('[type=submit]');
        if (btn) {
          setTimeout(function () {
            btn.disabled = true;
            btn.textContent = btn.dataset.loading || 'Please wait…';
          }, 0);
        }
      });
    });
  }

  /* ---- Mobile sidebar toggle (admin) ------------------------------------ */
  function initSidebar() {
    var toggle = document.querySelector('[data-sidebar-toggle]');
    var bar    = document.querySelector('.sidebar');
    if (!toggle || !bar) return;
    toggle.addEventListener('click', function () {
      bar.classList.toggle('open');
      toggle.setAttribute('aria-expanded', bar.classList.contains('open') ? 'true' : 'false');
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initTheme();
    initMenus();
    initConfirms();
    initFlashes();
    initSlotPicker();
    initDayStrip();
    initPlayerSearch();
    initAutoFilters();
    initSubmitGuard();
    initSidebar();
  });
})();
