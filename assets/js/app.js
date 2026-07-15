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
  // ---------- password visibility toggle ----------
  document.querySelectorAll('input[type="password"]').forEach(function (input) {
    var btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'pwd-toggle';
    btn.setAttribute('aria-label', 'Show password');
    var eye  = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    var off  = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    btn.innerHTML = eye;
    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.innerHTML = show ? off : eye;
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
    input.parentNode.insertBefore(btn, input.nextSibling);
  });
})();
