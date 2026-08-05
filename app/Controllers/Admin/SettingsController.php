<?php

namespace App\Controllers\Admin;

/**
 * SettingsController — CMS-editable business information.
 * Stores hours, contact, address, hero copy, and social links in /settings.
 * The owner can update these without touching code.
 */
class SettingsController {

    public static function render(): void {
        security_headers();

        $db = getDB();

        // Default settings if /settings is empty
        $defaults = [
            'business_name'   => BRAND_NAME,
            'tagline'         => BRAND_TAGLINE,
            'address'         => 'Mabolo, Iloilo City Proper, Iloilo City, Philippines',
            'phone'           => '+63 (033) 320-0000',
            'hours'           => 'Mon–Sun · 10:00 AM – 11:00 PM',
            'facebook_url'    => '',
            'instagram_url'   => '',
            'support_email'   => '',
            'hero_title'      => "Your table is waiting.",
            'hero_subtitle'   => "Order ahead for pickup, reserve a table, or book equipment for your next celebration — all from one account.",
            'about_headline'  => 'Your trusted partner for events and celebrations.',
            'about_body'      => "From everyday meals to special gatherings, we bring quality food and reliable rental equipment to every table we serve in Iloilo City.",
            'about_stat1_num' => '10+',
            'about_stat1_lbl' => 'Years Experience',
            'about_stat2_num' => '500+',
            'about_stat2_lbl' => 'Events Served',
            'about_stat3_num' => '100%',
            'about_stat3_lbl' => 'Satisfaction',
            'gcash_number'    => '0917 000 0000',
            'gcash_qr'        => '',
        ];

        $settings = $db->retrieve('/settings');
        if (!is_array($settings) || empty($settings)) {
            $settings = $defaults;
        } else {
            $settings = array_merge($defaults, $settings);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $updated = [];
            foreach ($defaults as $key => $default) {
                if ($key === 'gcash_qr') continue;
                $updated[$key] = trim(post($key, $default));
            }
            // Optional QR image upload (replaces the stored value only when a new file is sent)
            $qr = save_upload('gcash_qr', UPLOAD_ROOT . '/settings', ['jpg', 'jpeg', 'png', 'webp'], 2);
            if ($qr !== null) {
                $updated['gcash_qr'] = $qr;
            } elseif (post('gcash_qr_remove') === '1') {
                $updated['gcash_qr'] = '';
            } else {
                $updated['gcash_qr'] = trim((string)($settings['gcash_qr'] ?? ''));
            }
            try {
                // PATCH the /settings node directly (no child id)
                $db->updateNode('/settings', $updated);
                cache_file_forget('business_settings');
                flash('Business settings saved.', 'ok');
            } catch (Exception $e) {
                flash('Could not save settings: ' . $e->getMessage(), 'danger');
            }
            redirect('/admin/settings.php');
        }

        $pageTitle = 'Business Settings';
        $activeNav = 'settings';
        $layout    = '';
        require_once dirname(__DIR__, 3) . '/includes/header.php';

        // Add settings to the admin nav by merging into navItems
        // (header.php already built $navItems; we add 'Settings' if not present)
        ?>

        <div class="page-head">
          <span class="eyebrow">Configuration</span>
          <h1>Business settings</h1>
          <p>Update your diner's contact info, hours, and marketing copy. Changes appear instantly across the site.</p>
        </div>

        <form method="post" action="/admin/settings.php" class="form-grid">
          <?= csrf_field() ?>

          <div class="card card--pad">
            <div class="card__head"><h3>Contact &amp; location</h3></div>
            <div class="card__body form-grid">
              <div class="form-grid--2">
                <div class="field">
                  <label for="address">Address</label>
                  <input class="input" id="address" name="address" value="<?= e($settings['address']) ?>">
                </div>
                <div class="field">
                  <label for="phone">Phone / reservations</label>
                  <input class="input" id="phone" name="phone" value="<?= e($settings['phone']) ?>">
                </div>
              </div>
              <div class="field">
                <label for="hours">Operating hours</label>
                <input class="input" id="hours" name="hours" value="<?= e($settings['hours']) ?>">
                <span class="hint">Shown on the login page and About page.</span>
              </div>
              <div class="field">
                <label for="support_email">Support email (for "Report a problem")</label>
                <input class="input" id="support_email" name="support_email" type="email" value="<?= e($settings['support_email']) ?>">
                <span class="hint">If set, a "Report a problem" link appears in the footer.</span>
              </div>
            </div>
          </div>

          <div class="card card--pad">
            <div class="card__head"><h3>Social media</h3></div>
            <div class="card__body form-grid">
              <div class="form-grid--2">
                <div class="field">
                  <label for="facebook_url">Facebook page URL</label>
                  <input class="input" id="facebook_url" name="facebook_url" type="url" placeholder="https://facebook.com/..." value="<?= e($settings['facebook_url']) ?>">
                </div>
                <div class="field">
                  <label for="instagram_url">Instagram URL</label>
                  <input class="input" id="instagram_url" name="instagram_url" type="url" placeholder="https://instagram.com/..." value="<?= e($settings['instagram_url']) ?>">
                </div>
              </div>
            </div>
          </div>

          <div class="card card--pad">
            <div class="card__head"><h3>GCash payments</h3></div>
            <div class="card__body form-grid">
              <div class="field">
                <label for="gcash_number">GCash number</label>
                <input class="input" id="gcash_number" name="gcash_number" value="<?= e($settings['gcash_number']) ?>" placeholder="0917 000 0000">
                <span class="hint">Shown to customers when they choose GCash at checkout.</span>
              </div>
              <div class="field">
                <label for="gcash_qr">GCash QR code (optional)</label>
                <?php $gcashQr = trim((string)($settings['gcash_qr'] ?? '')); ?>
                <?php if ($gcashQr !== ''): ?>
                  <div style="margin:4px 0 10px">
                    <img src="<?= e(image_display_src($gcashQr, 'settings')) ?>" alt="Current GCash QR" style="max-width:140px;height:auto;border-radius:8px;background:#fff;padding:6px;border:1px solid var(--line)">
                    <label class="checkbox-row" style="margin-top:6px">
                      <input type="checkbox" name="gcash_qr_remove" value="1"> Remove current QR
                    </label>
                  </div>
                <?php endif; ?>
                <input class="input" type="file" id="gcash_qr" name="gcash_qr" accept="image/jpeg,image/png,image/webp">
                <span class="hint">Upload a QR image (JPG, PNG, WebP, max 2MB). It appears next to the number at checkout.</span>
              </div>
            </div>
          </div>

          <div class="card card--pad">
            <div class="card__head"><h3>Login &amp; About copy</h3></div>
            <div class="card__body form-grid">
              <div class="field">
                <label for="hero_title">Login headline</label>
                <input class="input" id="hero_title" name="hero_title" value="<?= e($settings['hero_title']) ?>">
              </div>
              <div class="field">
                <label for="hero_subtitle">Login subtitle</label>
                <textarea class="textarea" id="hero_subtitle" name="hero_subtitle"><?= e($settings['hero_subtitle']) ?></textarea>
              </div>
              <div class="field">
                <label for="about_headline">About headline</label>
                <input class="input" id="about_headline" name="about_headline" value="<?= e($settings['about_headline']) ?>">
              </div>
              <div class="field">
                <label for="about_body">About intro paragraph</label>
                <textarea class="textarea" id="about_body" name="about_body"><?= e($settings['about_body']) ?></textarea>
              </div>
              <div class="form-grid--2">
                <div class="field">
                  <label for="about_stat1_num">Stat 1 number</label>
                  <input class="input" id="about_stat1_num" name="about_stat1_num" value="<?= e($settings['about_stat1_num']) ?>">
                </div>
                <div class="field">
                  <label for="about_stat1_lbl">Stat 1 label</label>
                  <input class="input" id="about_stat1_lbl" name="about_stat1_lbl" value="<?= e($settings['about_stat1_lbl']) ?>">
                </div>
              </div>
              <div class="form-grid--2">
                <div class="field">
                  <label for="about_stat2_num">Stat 2 number</label>
                  <input class="input" id="about_stat2_num" name="about_stat2_num" value="<?= e($settings['about_stat2_num']) ?>">
                </div>
                <div class="field">
                  <label for="about_stat2_lbl">Stat 2 label</label>
                  <input class="input" id="about_stat2_lbl" name="about_stat2_lbl" value="<?= e($settings['about_stat2_lbl']) ?>">
                </div>
              </div>
              <div class="form-grid--2">
                <div class="field">
                  <label for="about_stat3_num">Stat 3 number</label>
                  <input class="input" id="about_stat3_num" name="about_stat3_num" value="<?= e($settings['about_stat3_num']) ?>">
                </div>
                <div class="field">
                  <label for="about_stat3_lbl">Stat 3 label</label>
                  <input class="input" id="about_stat3_lbl" name="about_stat3_lbl" value="<?= e($settings['about_stat3_lbl']) ?>">
                </div>
              </div>
            </div>
          </div>

          <div class="form-actions">
            <button class="btn btn--gold btn--lg" type="submit">Save settings</button>
            <a class="btn btn--ghost" href="/admin/">Cancel</a>
          </div>
        </form>

        <?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
        <?php
    }
}
