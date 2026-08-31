  </main><!-- /.app-main -->

  <footer class="app-footer">
    <div class="app-footer__inner">
      <div class="app-footer__social">
        <?php
        $s = get_settings();
        $fb = $s['facebook_url'] ?? '#';
        $ig = $s['instagram_url'] ?? '#';
        $supportEmail = $s['support_email'] ?? '';
        ?>
        <a href="https://web.facebook.com/cratesnplatesdiner/?_rdc=1&_rdr#" target="_blank" rel="noopener" aria-label="Facebook" class="app-footer__social-link">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
        </a>

      </div>
      <?php if ($supportEmail): ?>
        <a href="mailto:<?= e($supportEmail) ?>" class="app-footer__help">Report a problem</a>
      <?php endif; ?>
      <small>&copy; <?= date('Y') ?> <?= e(BRAND_NAME) ?>. All rights reserved.</small>
    </div>
  </footer>
</div><!-- /.app-shell -->
<script src="/assets/js/app.js"></script>
</body>
</html>
