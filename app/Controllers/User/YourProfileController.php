<?php

namespace App\Controllers\User;

/**
 * YourProfileController — customer profile view + edit name + upload avatar.
 * Protected by require_user(). Uses the standard header/footer shell.
 */
class YourProfileController {

    public static function render(): void {
        $db   = getDB();
        $uid  = (string) ($_SESSION['user_id'] ?? '');
        $user = rows($db->retrieve('/user/' . $uid));

        if (!is_array($user) || !isset($user['email'])) {
            // Stale session — force re-auth.
            $_SESSION = [];
            session_destroy();
            if (session_status() === PHP_SESSION_NONE) session_start();
            flash('Your session expired. Please sign in again.', 'warn');
            redirect('/user/login.php');
        }

        // ---------- Edit name ----------
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action', '') === 'update_name') {
            csrf_verify();
            $name = trim((string) post('name', ''));
            if ($name === '') {
                flash('Name cannot be empty.', 'danger');
            } else {
                try {
                    $db->update('/user', $uid, ['name' => $name]);
                    $_SESSION['user_name'] = $name;
                    $user['name'] = $name;
                    flash('Your name has been updated.', 'ok');
                } catch (Throwable $e) {
                    flash('Could not save your name. Please try again.', 'danger');
                }
            }
            redirect('/user/your_profile.php');
        }

        // ---------- Change password ----------
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action', '') === 'change_password') {
            csrf_verify();
            $current = (string) post('current_password', '');
            $new     = (string) post('new_password', '');
            $confirm = (string) post('confirm_password', '');
            $isGoogle = ($user['provider'] ?? '') === 'google';

            if (!$isGoogle && $current === '') {
                flash('Please enter your current password.', 'danger');
            } elseif ($new === '') {
                flash('New password cannot be empty.', 'danger');
            } elseif (strlen($new) < 8) {
                flash('New password must be at least 8 characters.', 'danger');
            } elseif ($new !== $confirm) {
                flash('New passwords do not match.', 'danger');
            } else {
                $ok = true;
                if (!$isGoogle) {
                    $hash = $user['password_hash'] ?? '';
                    if (!password_verify($current, $hash)) {
                        flash('Current password is incorrect.', 'danger');
                        $ok = false;
                    }
                }
                if ($ok) {
                    try {
                        $db->update('/user', $uid, ['password_hash' => password_hash($new, PASSWORD_BCRYPT)]);
                        flash('Password updated successfully.', 'ok');
                    } catch (Throwable $e) {
                        flash('Could not update password. Please try again.', 'danger');
                    }
                }
            }
            redirect('/user/your_profile.php');
        }

        // ---------- Upload avatar ----------
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action', '') === 'upload_image') {
            csrf_verify();
            try {
                $filename = save_upload('profile_image', UPLOAD_ROOT . '/user/profile');
                if ($filename === null) {
                    flash('Please choose an image file to upload.', 'warn');
                } else {
                    $db->update('/user', $uid, ['profile_image' => $filename]);
                    $_SESSION['user_image'] = $filename;
                    $user['profile_image'] = $filename;
                    flash('Profile photo updated.', 'ok');
                }
            } catch (Throwable $e) {
                flash($e->getMessage(), 'danger');
            }
            redirect('/user/your_profile.php');
        }

        $pageTitle = 'Your profile';
        $activeNav = 'profile';
        $layout    = 'wide';
        require_once dirname(__DIR__, 3) . '/includes/header.php';

        $avatarUrl = upload_web('user/profile', $user['profile_image'] ?? '');
        $userName  = $user['name']    ?? '';
        $userEmail = $user['email']   ?? '';
        $provider  = $user['provider'] ?? 'email';
        $verified  = !empty($user['email_verified']);
        ?>

        <div class="page-head">
          <div class="page-head__row">
            <div>
              <span class="eyebrow">Account</span>
              <h1>Your profile</h1>
              <p>Review your details, update your name, or change your profile photo.</p>
            </div>
            <a class="btn btn--outline" href="/user/products.php">Back to shop</a>
          </div>
        </div>

        <div class="grid grid--cards">

          <!-- Profile card -->
          <section class="card card--pad">
            <div class="t-center" style="padding:8px 0 4px;">
              <img src="<?= e($avatarUrl) ?>" alt="<?= e($userName) ?> avatar"
                   width="120" height="120"
                   style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid var(--surface);box-shadow:var(--shadow);background:var(--bg-2);">
              <h2 style="margin-top:14px;margin-bottom:2px;"><?= e($userName ?: 'Unnamed guest') ?></h2>
              <p class="muted mt-0" style="margin:0;"><?= e($userEmail) ?></p>
              <div class="row" style="justify-content:center;gap:8px;margin-top:10px;">
                <?php if ($verified): ?>
                  <span class="badge badge--ok">Verified</span>
                <?php else: ?>
                  <span class="badge badge--warn">Unverified</span>
                <?php endif; ?>
                <?php if ($provider === 'google'): ?>
                  <span class="badge badge--info">Google account</span>
                <?php else: ?>
                  <span class="badge badge--muted">Email account</span>
                <?php endif; ?>
              </div>
            </div>

            <hr class="divider">

            <dl style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:8px 14px;font-size:14px;">
              <dt class="muted">Member since</dt>
              <dd style="margin:0;"><?= e($user['created_at'] ?? '—') ?></dd>
              <dt class="muted">Sign-in method</dt>
              <dd style="margin:0;"><?= e($provider === 'google' ? 'Google' : 'Email &amp; password') ?></dd>
            </dl>

            <hr class="divider">
            <a class="btn btn--outline btn--block" href="/user/logout.php">Sign out</a>
          </section>

          <!-- Edit column -->
          <section class="col">

            <div class="card">
              <div class="card__head">
                <h3>Edit name</h3>
              </div>
              <div class="card__body">
                <form method="post" action="/user/your_profile.php" novalidate>
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="update_name">
                  <div class="form-grid">
                    <div class="field">
                      <label for="name">Display name</label>
                      <input class="input" id="name" name="name" type="text" autocomplete="name"
                             value="<?= e($userName) ?>" required>
                    </div>
                    <div class="form-actions">
                      <button class="btn btn--gold" type="submit">Save name</button>
                    </div>
                  </div>
                </form>
              </div>
            </div>

            <div class="card">
              <div class="card__head">
                <h3>Profile photo</h3>
              </div>
              <div class="card__body">
                <form method="post" action="/user/your_profile.php" enctype="multipart/form-data" novalidate>
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="upload_image">
                  <div class="form-grid">
                    <div class="field">
                      <label for="profile_image">Choose a new photo</label>
                      <input class="input" id="profile_image" name="profile_image" type="file"
                             accept="image/jpeg,image/png,image/webp">
                      <span class="hint">JPG, PNG, or WebP. Maximum 5 MB.</span>
                    </div>
                    <div class="form-actions">
                      <button class="btn btn--gold" type="submit">Upload photo</button>
                    </div>
                  </div>
                </form>
              </div>
            </div>

            <?php if ($provider !== 'google'): ?>
            <div class="card">
              <div class="card__head">
                <h3>Change password</h3>
              </div>
              <div class="card__body">
                <form method="post" action="/user/your_profile.php" novalidate>
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="change_password">
                  <div class="form-grid">
                    <div class="field">
                      <label for="current_password">Current password</label>
                      <input class="input" id="current_password" name="current_password" type="password"
                             autocomplete="current-password" required>
                    </div>
                    <div class="field">
                      <label for="new_password">New password</label>
                      <input class="input" id="new_password" name="new_password" type="password"
                             autocomplete="new-password" required minlength="8">
                      <span class="hint">At least 8 characters.</span>
                    </div>
                    <div class="field">
                      <label for="confirm_password">Confirm new password</label>
                      <input class="input" id="confirm_password" name="confirm_password" type="password"
                             autocomplete="new-password" required minlength="8">
                    </div>
                    <div class="form-actions">
                      <button class="btn btn--gold" type="submit">Update password</button>
                    </div>
                  </div>
                </form>
              </div>
            </div>
            <?php endif; ?>

          </section>

        </div>

        <?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
        <?php
    }
}
