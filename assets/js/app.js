// Minimal vanilla JS — no frameworks. Mobile nav, theme, confirm guards, full AJAX cart.
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
  });

  // ====================================================================
  //  CART UTILITIES
  // ====================================================================

  function showCartToast(message) {
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

  function cartPost(form, extra) {
    var fd = new FormData(form);
    if (extra) {
      Object.keys(extra).forEach(function (k) { fd.set(k, extra[k]); });
    }
    return fetch(form.action || location.href, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); });
  }

  // ====================================================================
  //  PRODUCTS PAGE — AJAX Add to Cart
  // ====================================================================

  document.addEventListener('submit', function (e) {
    var form = e.target.closest('.ajax-add-to-cart');
    if (!form) return;
    e.preventDefault();

    var btn = form.querySelector('button[type="submit"]');
    if (btn) { btn.disabled = true; btn.textContent = 'Adding...'; }

    cartPost(form)
      .then(function (data) {
        if (data.success) {
          showCartToast(data.message || 'Item added to cart.');
          updateCartBadge(data.count || 0);
        }
      })
      .catch(function () { form.submit(); })
      .finally(function () {
        if (btn) { btn.disabled = false; btn.textContent = 'Add to Cart'; }
      });
  });

  // ====================================================================
  //  CART PAGE — Real-time quantity update + remove
  // ====================================================================

  // --- helper: update all visible totals on the page ---
  function refreshCartUI(data) {
    if (data.total !== undefined) {
      var totalEl = document.getElementById('cartTotal');
      if (totalEl) totalEl.textContent = data.total;
    }
    if (data.count !== undefined) updateCartBadge(data.count);
  }

  // --- helper: show empty cart state ---
  function showCartEmpty() {
    var wrap = document.getElementById('cartWrap');
    if (!wrap) return;
    wrap.outerHTML =
      '<div class="empty" id="cartEmpty">' +
        '<div class="empty__icon" aria-hidden="true">' +
          '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' +
            '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>' +
            '<path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>' +
          '</svg>' +
        '</div>' +
        '<h3>Your cart is empty</h3>' +
        '<p>Browse the menu and add a few plates to get started.</p>' +
        '<a class="btn btn--gold mt-2" href="/user/products.php">Browse the menu</a>' +
      '</div>';
  }

  // --- intercept quantity stepper forms (+/- buttons) on the cart page ---
  document.addEventListener('submit', function (e) {
    var form = e.target.closest('.qty');
    if (!form) return;

    var actionInput = form.querySelector('input[name="action"]');
    if (!actionInput || actionInput.value !== 'update') return;

    e.preventDefault();

    var row = form.closest('.cart-item');
    var delta = form.querySelector('button:focus, button:active');
    var deltaVal = delta ? delta.getAttribute('value') : '';
    var qtyInput = form.querySelector('input[name="qty"]');
    var typed = parseInt(qtyInput.value, 10) || 1;

    cartPost(form, { delta: deltaVal, qty: typed })
      .then(function (data) {
        if (!data.success) return;

        // server auto-removed the item (qty dropped to zero)
        if (data.removed) {
          if (row) {
            row.style.transition = 'opacity .3s ease, transform .3s ease';
            row.style.opacity = '0';
            row.style.transform = 'translateX(30px)';
            setTimeout(function () { row.remove(); }, 320);
          }
          refreshCartUI(data);
          showCartToast('Item removed from cart.');
          if (data.empty) setTimeout(showCartEmpty, 400);
          return;
        }

        // update the input value to reflect server state
        qtyInput.value = data.qty;

        // update subtotal for this row
        if (row && data.subtotal !== undefined) {
          var sub = row.querySelector('[data-subtotal]');
          if (sub) sub.textContent = data.subtotal;
        }

        // update page total + badge
        refreshCartUI(data);
        showCartToast('Cart updated.');
      })
      .catch(function () {
        // fallback: normal submit
        form.submit();
      });
  });

  // --- handle direct quantity input change (typed value, blur or Enter) ---
  document.addEventListener('change', function (e) {
    var qtyInput = e.target;
    if (qtyInput.tagName !== 'INPUT' || qtyInput.type !== 'number') return;

    var form = qtyInput.closest('.qty');
    if (!form) return;

    var actionInput = form.querySelector('input[name="action"]');
    if (!actionInput || actionInput.value !== 'update') return;

    var row = form.closest('.cart-item');
    var stock = row ? parseInt(row.getAttribute('data-stock'), 10) : 999;
    var val = parseInt(qtyInput.value, 10) || 1;
    val = Math.max(1, Math.min(stock, val));
    qtyInput.value = val;

    cartPost(form, { delta: '', qty: val })
      .then(function (data) {
        if (!data.success) return;

        qtyInput.value = data.qty;

        if (row && data.subtotal !== undefined) {
          var sub = row.querySelector('[data-subtotal]');
          if (sub) sub.textContent = data.subtotal;
        }

        refreshCartUI(data);
        showCartToast('Cart updated.');
      })
      .catch(function () {
        form.submit();
      });
  });

  // --- handle remove buttons on the cart page ---
  document.addEventListener('submit', function (e) {
    var form = e.target.closest('.cart-remove-form');
    if (!form) return;

    e.preventDefault();

    var row = form.closest('.cart-item');

    cartPost(form)
      .then(function (data) {
        if (!data.success) return;

        // fade out and remove the row
        if (row) {
          row.style.transition = 'opacity .3s ease, transform .3s ease';
          row.style.opacity = '0';
          row.style.transform = 'translateX(30px)';
          setTimeout(function () { row.remove(); }, 320);
        }

        refreshCartUI(data);
        showCartToast('Item removed from cart.');

        // if cart is now empty, show empty state
        if (data.empty) {
          setTimeout(showCartEmpty, 400);
        }
      })
      .catch(function () {
        form.submit();
      });
  });
})();
