// Minimal vanilla JS — no frameworks. Mobile nav, theme, confirm guards, AJAX cart.
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
    var eye = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
    var off = '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
    btn.innerHTML = eye;
    btn.addEventListener('click', function () {
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.innerHTML = show ? off : eye;
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
    input.parentNode.insertBefore(btn, input.nextSibling);
    input.classList.add('has-toggle');
  });

  // ====================================================================
  //  PRODUCTS PAGE — AJAX Add to Cart
  // ====================================================================

  function showToast(message) {
    var existing = document.querySelector('.cart-toast');
    if (existing) existing.remove();

    var toast = document.createElement('div');
    toast.className = 'cart-toast';
    toast.innerHTML = '<span class="cart-toast__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span><span>' + (message || 'Item added to cart.') + '</span>';
    document.body.appendChild(toast);

    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        toast.classList.add('is-visible');
      });
    });

    setTimeout(function () {
      toast.classList.remove('is-visible');
      setTimeout(function () { toast.remove(); }, 400);
    }, 2500);
  }

  function updateCartBadge(count) {
    var pill = document.querySelector('.cart-pill');
    if (!pill) return;
    var badge = pill.querySelector('.count');
    if (count > 0) {
      if (badge) {
        badge.textContent = count;
      } else {
        var span = document.createElement('span');
        span.className = 'count';
        span.textContent = count;
        pill.appendChild(span);
      }
    } else if (badge) {
      badge.remove();
    }
  }

  document.addEventListener('submit', function (e) {
    var form = e.target.closest('.ajax-add-to-cart');
    if (!form) return;
    e.preventDefault();

    var btn = form.querySelector('button[type="submit"]');
    if (btn) { btn.disabled = true; btn.textContent = 'Adding...'; }

    var fd = new FormData(form);
    fetch(form.action, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.success) {
        showToast(data.message || 'Item added to cart.');
        updateCartBadge(data.count || 0);
      }
    })
    .catch(function () { form.submit(); })
    .finally(function () {
      if (btn) { btn.disabled = false; btn.textContent = 'Add to Cart'; }
    });
  });
})();
