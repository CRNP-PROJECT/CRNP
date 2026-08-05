<?php

namespace App\Controllers\User;

use App\Models\Booking;
use App\Models\RentItem;

/**
 * BookingController — Customer rental booking.
 * Lists rent_items with a qty picker per row, validates appointment/return
 * windows, persists to /bookings, and decrements rent stock.
 *
 * Also handles ?cancel=<id> (GET or POST) for owner-scoped cancellations
 * that restore rent stock.
 */
class BookingController {

    public static function render(): void {
        $activeNav = 'rent';
        $pageTitle = 'Rent Tableware';
        $layout    = 'wide';

        /* ---------- Cancel a booking (GET or POST) ---------- */
        $cancelId = $_GET['cancel'] ?? post('cancel');
        if ($cancelId) {
            $booking = Booking::find($cancelId);
            if (!$booking) {
                flash('Booking not found.', 'danger');
                redirect('/user/your_orders.php');
            }
            if (strcasecmp((string)($booking->user_email ?? ''), user_email()) !== 0) {
                flash('You can only cancel your own bookings.', 'danger');
                redirect('/user/your_orders.php');
            }
            $status = (string)($booking->status ?? '');
            if ($status !== 'pending') {
                flash('That booking can no longer be cancelled.', 'warn');
                redirect('/user/your_orders.php');
            }
            /* Restore stock using the Firebase KEY stored in items. */
            $items = $booking->get('items') ?? [];
            if (is_array($items)) {
                foreach ($items as $itemId => $row) {
                    if (!is_array($row)) continue;
                    RentItem::restoreStock((string)$itemId, (int)($row['qty'] ?? 0));
                }
            }
            try {
                Booking::find($cancelId)->update([
                    'status'       => 'cancelled',
                    'cancelled_at' => now(),
                ]);
                flash('Booking cancelled. Stock has been returned.', 'ok');
            } catch (Throwable $ex) {
                flash('Stock was restored but the status update failed: ' . $ex->getMessage(), 'warn');
            }
            redirect('/user/your_orders.php');
        }

        /* ---------- POST: create booking ---------- */
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();
            $full_name        = trim(post('full_name'));
            $contact          = trim(post('contact'));
            $address          = trim(post('address'));
            $appointment_time = trim(post('appointment_time'));
            $return_time      = trim(post('return_time'));
            $method           = post('payment_method', 'counter');
            if (!in_array($method, ['gcash', 'counter'], true)) $method = 'counter';

            $errors = [];
            if ($full_name === '')        $errors[] = 'Please enter your full name.';
            if ($contact   === '')        $errors[] = 'Please enter a contact number.';
            if ($address   === '')        $errors[] = 'Please enter a pickup or delivery address.';
            if ($appointment_time === '' || $return_time === '') {
                $errors[] = 'Please choose both appointment and return times.';
            } elseif ($appointment_time >= $return_time) {
                $errors[] = 'Return time must be after the appointment time.';
            }

            /* Collect requested qtys and re-fetch rent_items for fresh stock. */
            $qtys = post('qtys', []);
            if (!is_array($qtys)) $qtys = [];
            $rentItems = RentItem::raw();

            $items = [];
            $total = 0.0;
            foreach ($qtys as $itemId => $q) {
                $q = (int) $q;
                if ($q <= 0) continue;
                $itemId = (string) $itemId;
                if (!isset($rentItems[$itemId])) {
                    $errors[] = 'One of the selected items is no longer available.';
                    continue;
                }
                $row   = $rentItems[$itemId];
                $avail = (int) ($row['quantity'] ?? 0);
                $label = $row['display_name'] ?? $row['name'] ?? 'Item';
                if ($avail <= 0) {
                    $errors[] = '"' . $label . '" is no longer available.';
                    continue;
                }
                if ($q > $avail) {
                    $errors[] = 'Only ' . $avail . ' of "' . $label . '" are available.';
                    continue;
                }
                $price = (float) ($row['price'] ?? 0);
                $sub   = $price * $q;
                $items[$itemId] = [
                    'name'     => $label,
                    'qty'      => $q,
                    'price'    => $price,
                    'subtotal' => $sub,
                ];
                $total += $sub;
            }

            if (!$items && !$errors) {
                $errors[] = 'Please choose at least one item to book.';
            }

            /* Receipt upload — only when everything else validates. */
            $receipt = null;
            if (!$errors && $method === 'gcash') {
                if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] === UPLOAD_ERR_NO_FILE) {
                    $errors[] = 'Please upload your GCash receipt.';
                } else {
                    try {
                        $receipt = upload_to_base64('receipt', UPLOAD_ROOT . '/user/bookings');
                        if (!$receipt) {
                            $errors[] = 'Receipt upload failed. Please try again.';
                        }
                    } catch (Throwable $ex) {
                        $errors[] = $ex->getMessage();
                    }
                }
            }

            if (!$errors) {
                $payment_status = $method === 'gcash' ? 'pending_verification' : 'no_payment_required';
                $booking = [
                    'user_id'         => $_SESSION['user_id'] ?? '',
                    'user_email'      => user_email(),
                    'user_name'       => user_name(),
                    'items'           => $items,
                    'total'           => $total,
                    'appointment_time'=> $appointment_time,
                    'return_time'     => $return_time,
                    'full_name'       => $full_name,
                    'contact'         => $contact,
                    'address'         => $address,
                    'payment_method'  => $method,
                    'payment_status'  => $payment_status,
                    'receipt'         => $receipt,
                    'status'          => 'pending',
                    'created_at'      => now(),
                ];

                try {
                    $newId = (new Booking($booking))->save();
                    if (!$newId) {
                        throw new RuntimeException('Firebase did not return a booking id.');
                    }
                    /* Decrement rent stock after a successful insert. */
                    foreach ($items as $itemId => $row) {
                        RentItem::decrementStock((string)$itemId, (int)$row['qty']);
                    }
                    flash('Booking request submitted! We will confirm shortly.', 'ok');
                    redirect('/user/your_orders.php');
                } catch (Throwable $ex) {
                    flash('Could not submit your booking: ' . $ex->getMessage(), 'danger');
                }
            } else {
                foreach ($errors as $err) {
                    flash($err, 'danger');
                }
            }
        }

        /* ---------- Render ---------- */
        $rentItems = RentItem::raw();
        uasort($rentItems, function ($a, $b) {
            return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        require_once dirname(__DIR__, 3) . '/includes/header.php';
        ?>

        <style>
          .rent-thumb { width:52px; height:52px; border-radius:9px; object-fit:cover; border:1px solid var(--line); background:var(--bg-2); cursor:pointer; transition:transform .15s; }
          .rent-thumb:hover { transform:scale(1.08); }

          /* lightbox overlay */
          .lightbox-overlay { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.78); align-items:center; justify-content:center; cursor:zoom-out; }
          .lightbox-overlay.active { display:flex; }
          .lightbox-overlay img { max-width:90vw; max-height:85vh; border-radius:12px; box-shadow:0 8px 40px rgba(0,0,0,.55); }
          .lightbox-close { position:absolute; top:18px; right:24px; width:40px; height:40px; border-radius:50%; border:0; background:rgba(255,255,255,.15); color:#fff; font-size:22px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .15s; }
          .lightbox-close:hover { background:rgba(255,255,255,.3); }
        </style>

        <!-- lightbox container -->
        <div class="lightbox-overlay" id="lightbox">
          <button class="lightbox-close" id="lightbox-close" aria-label="Close">&times;</button>
          <img id="lightbox-img" src="" alt="Preview">
        </div>

        <div class="page-head">
          <span class="eyebrow">Rentals · For private dinners &amp; events</span>
          <h1>Rent tableware &amp; equipment</h1>
          <p>Reserve pieces from our cellar. Pick your appointment and return times, and we'll have everything ready for you.</p>
        </div>

        <form method="post" enctype="multipart/form-data" class="form-grid" novalidate>
          <?= csrf_field() ?>
          <div class="card">
            <div class="card__head">
              <h2>Choose items</h2>
              <small class="muted">Enter 0 to skip an item</small>
            </div>
            <div class="card__body" style="padding:0">
              <?php if (!$rentItems): ?>
                <div class="empty" style="border:0">
                  <div class="empty__icon" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>
                    </svg>
                  </div>
                  <h3>No rental items available</h3>
                  <p>The cellar is being restocked — please check back soon.</p>
                </div>
              <?php else: ?>
                <div class="table-wrap" style="border:0">
                  <table class="tbl">
                    <thead>
                      <tr><th></th><th>Item</th><th>Description</th><th class="num">Rate</th><th class="num">Available</th><th class="num">Qty</th></tr>
                    </thead>
                    <tbody>
                      <?php foreach ($rentItems as $id => $r):
                        $avail = (int) ($r['quantity'] ?? 0);
                        $label = $r['display_name'] ?? $r['name'] ?? 'Item';
                        $desc  = trim($r['description'] ?? '');
                        $prev  = isset($_POST['qtys'][$id]) ? (int) $_POST['qtys'][$id] : 0;
                      ?>
                        <tr>
                          <td>
                            <?php $imgSrc = product_image_url($r['image'] ?? '', $id, 'rent_items'); ?>
                            <img class="rent-thumb" src="<?= e($imgSrc) ?>" alt="<?= e($label) ?>" data-lightbox="<?= e($imgSrc) ?>">
                          </td>
                          <td>
                            <strong><?= e($label) ?></strong>
                            <?php if (!empty($r['display_name']) && !empty($r['name']) && $r['display_name'] !== $r['name']): ?>
                              <div class="muted" style="font-size:12px"><?= e($r['name']) ?></div>
                            <?php endif; ?>
                          </td>
                          <td class="muted"><?= $desc !== '' ? e($desc) : '—' ?></td>
                          <td class="num"><?= money($r['price'] ?? 0) ?></td>
                          <td class="num <?= $avail <= 0 ? 'muted' : '' ?>"><?= $avail ?></td>
                          <td class="num">
                            <input class="input" type="number" name="qtys[<?= e($id) ?>]" value="<?= $prev ?>" min="0" max="<?= max(0, $avail) ?>" style="width:84px;text-align:center" <?= $avail <= 0 ? 'disabled' : '' ?> inputmode="numeric">
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="card card--pad">
            <div class="card__head" style="padding:0 0 14px;border-bottom:1px solid var(--line-2);margin-bottom:18px"><h2>Appointment &amp; details</h2></div>

            <div class="form-grid">
              <div class="form-grid--2">
                <div class="field">
                  <label for="appointment_time">Appointment time</label>
                  <input class="input" type="datetime-local" id="appointment_time" name="appointment_time" value="<?= e(post('appointment_time')) ?>" required>
                </div>
                <div class="field">
                  <label for="return_time">Return time</label>
                  <input class="input" type="datetime-local" id="return_time" name="return_time" value="<?= e(post('return_time')) ?>" required>
                </div>
              </div>

              <div class="form-grid--2">
                <div class="field">
                  <label for="full_name">Full name</label>
                  <input class="input" type="text" id="full_name" name="full_name" value="<?= e(post('full_name', user_name())) ?>" required autocomplete="name">
                </div>
                <div class="field">
                  <label for="contact">Contact number</label>
                  <input class="input" type="tel" id="contact" name="contact" value="<?= e(post('contact')) ?>" required autocomplete="tel" placeholder="0917 000 0000">
                </div>
              </div>

              <div class="field">
                <label for="address">Pickup / delivery address</label>
                <textarea class="textarea" id="address" name="address" required autocomplete="street-address" placeholder="House no., street, barangay, city"><?= e(post('address')) ?></textarea>
              </div>

              <div class="field">
                <label>Payment method</label>
                <div class="row">
                  <label class="checkbox-row"><input type="radio" name="payment_method" value="counter" <?= post('payment_method', 'counter') === 'counter' ? 'checked' : '' ?> data-pay="counter"> Pay at counter</label>
                  <label class="checkbox-row"><input type="radio" name="payment_method" value="gcash"   <?= post('payment_method')          === 'gcash'   ? 'checked' : '' ?> data-pay="gcash"> GCash</label>
                </div>
              </div>

              <div class="field" id="receipt-field" style="display:none">
                <?= gcash_payment_info_html() ?>
                <label for="receipt">GCash receipt</label>
                <input class="input" type="file" id="receipt" name="receipt" accept="image/png,image/jpeg,image/webp">
                <span class="hint">Upload a screenshot of your GCash transfer. JPG, PNG, or WebP (max 5MB).</span>
              </div>
            </div>

            <div class="form-actions" style="margin-top:20px">
              <button class="btn btn--gold btn--lg" type="submit">Submit booking request</button>
            </div>
          </div>
        </form>

        <script>
        (function () {
          var counter = document.querySelector('[data-pay="counter"]');
          var gcash   = document.querySelector('[data-pay="gcash"]');
          var field   = document.getElementById('receipt-field');
          var receipt = document.getElementById('receipt');
          if (!counter || !gcash || !field || !receipt) return;
          function sync() {
            var isGcash = gcash.checked;
            field.style.display = isGcash ? '' : 'none';
            receipt.required    = isGcash;
          }
          counter.addEventListener('change', sync);
          gcash.addEventListener('change', sync);
          sync();
        })();
        </script>

        <script>
        (function () {
          var overlay   = document.getElementById('lightbox');
          var lightImg  = document.getElementById('lightbox-img');
          var closeBtn  = document.getElementById('lightbox-close');
          if (!overlay || !lightImg) return;

          function openLightbox(src) {
            lightImg.src = src;
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
          }
          function closeLightbox() {
            overlay.classList.remove('active');
            lightImg.src = '';
            document.body.style.overflow = '';
          }

          document.querySelectorAll('[data-lightbox]').forEach(function (el) {
            el.addEventListener('click', function () {
              var src = el.getAttribute('data-lightbox');
              if (src) openLightbox(src);
            });
          });

          overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target === closeBtn) closeLightbox();
          });
          closeBtn.addEventListener('click', closeLightbox);
          document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeLightbox();
          });
        })();
        </script>

        <?php require_once dirname(__DIR__, 3) . '/includes/footer.php'; ?>
        <?php
    }
}
