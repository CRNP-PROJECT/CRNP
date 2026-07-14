// Minimal vanilla JS — no frameworks. Mobile nav toggle, theme toggle, confirm guards.
(function () {
  // ---------- mobile nav toggle ----------
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-nav-toggle]');
    if (t) {
      var nav = document.getElementById('topnav');
      if (nav) {
        var open = nav.classList.toggle('is-open');
        t.setAttribute('aria-expanded', open ? 'true' : 'false');
      }
    }
    // close nav when clicking a link inside it (mobile)
    var link = e.target.closest('#topnav a');
    if (link) {
      var nav2 = document.getElementById('topnav');
      if (nav2) nav2.classList.remove('is-open');
      var btn = document.querySelector('[data-nav-toggle]');
      if (btn) btn.setAttribute('aria-expanded', 'false');
    }
  });

  // ---------- theme toggle ----------
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem('ss-theme', theme); } catch (e) {}
    var btn = document.querySelector('[data-theme-toggle]');
    if (btn) btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-theme-toggle]');
    if (t) {
      var current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
      applyTheme(current === 'dark' ? 'light' : 'dark');
    }
  });

  // ---------- confirm destructive actions ----------
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-confirm]');
    if (el) {
      if (!window.confirm(el.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    }
  });

  // ---------- auto-dismiss flash alerts ----------
  setTimeout(function () {
    document.querySelectorAll('.alert').forEach(function (a) {
      a.style.transition = 'opacity .4s ease';
      a.style.opacity = '0';
      setTimeout(function () { a.remove(); }, 420);
    });
  }, 6000);
})();
