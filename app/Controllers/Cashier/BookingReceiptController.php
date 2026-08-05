<?php

namespace App\Controllers\Cashier;

use App\Models\Booking;

/**
 * cashier/booking_receipt.php — Printable booking receipt.
 */
class BookingReceiptController {

    public static function render(): void {
        $bookingId = trim((string)($_GET['id'] ?? ''));
        if ($bookingId === '') {
            flash('No booking specified.', 'danger');
            redirect('/cashier/bookings.php');
        }

        $bookingModel = Booking::find($bookingId);
        if (!$bookingModel) {
            flash('Booking not found.', 'danger');
            redirect('/cashier/bookings.php');
        }
        $b = $bookingModel->toArray();

        $items         = $b['items'] ?? [];
        $total         = (float)($b['total'] ?? 0);
        $paymentMethod = (string)($b['payment_method'] ?? 'counter');
        $customerName  = e($b['customer_name'] ?? $b['full_name'] ?? $b['user_name'] ?? '—');
        $contact       = e($b['contact'] ?? '—');
        $address       = e($b['address'] ?? '—');
        $createdAt     = $b['created_at'] ? e(date('M j, Y \a\t g:i A', strtotime($b['created_at']))) : '—';
        $apptTime      = $b['appointment_time'] ? e(date('M j, Y \a\t g:i A', strtotime($b['appointment_time']))) : '—';
        $retTime       = $b['return_time']      ? e(date('M j, Y \a\t g:i A', strtotime($b['return_time'])))      : '—';
        $createdBy     = e($b['created_by'] ?? ($b['user_name'] ?? '—'));
        $shortId       = strtoupper(substr($bookingId, 0, 6));

        $pageTitle = 'Booking Receipt #' . $shortId;
        ?><!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title><?= e(BRAND_NAME) ?> — Booking Receipt #<?= e($shortId) ?></title>
          <style>
            *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
            body { font-family:'Segoe UI',system-ui,-apple-system,sans-serif; background:#f5f3ef; color:#1a1a1a; display:flex; flex-direction:column; align-items:center; padding:32px 16px; min-height:100vh; }
            .no-print { display:flex; gap:12px; margin-bottom:24px; }
            .no-print .btn { display:inline-flex; align-items:center; gap:6px; padding:10px 20px; border:0; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; transition:.15s; }
            .no-print .btn--gold { background:#c8a44e; color:#fff; }
            .no-print .btn--gold:hover { background:#b8943a; }
            .no-print .btn--outline { background:transparent; border:1px solid #c8a44e; color:#c8a44e; }
            .no-print .btn--outline:hover { background:#c8a44e; color:#fff; }
            .receipt { width:100%; max-width:400px; background:#fff; border-radius:12px; padding:32px 28px; box-shadow:0 4px 24px rgba(0,0,0,.08); }
            .receipt__header { text-align:center; border-bottom:2px dashed #ddd; padding-bottom:20px; margin-bottom:20px; }
            .receipt__brand { font-size:22px; font-weight:800; letter-spacing:.04em; color:#c8a44e; }
            .receipt__tagline { font-size:12px; color:#888; margin-top:2px; }
            .receipt__title { font-size:13px; color:#555; margin-top:8px; text-transform:uppercase; letter-spacing:.1em; }
            .receipt__meta { display:grid; grid-template-columns:auto 1fr; gap:4px 12px; font-size:13px; margin-bottom:16px; }
            .receipt__meta dt { color:#888; }
            .receipt__meta dd { text-align:right; font-weight:500; }
            .receipt__items { width:100%; border-collapse:collapse; font-size:13px; margin-bottom:16px; }
            .receipt__items th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:#888; padding:6px 4px; border-bottom:1px solid #eee; }
            .receipt__items th.num { text-align:right; }
            .receipt__items td { padding:6px 4px; border-bottom:1px solid #f0f0f0; }
            .receipt__items td.num { text-align:right; white-space:nowrap; }
            .receipt__items td.name { max-width:200px; overflow:hidden; text-overflow:ellipsis; }
            .receipt__total { border-top:2px solid #333; padding-top:10px; display:flex; justify-content:space-between; font-size:16px; font-weight:700; margin-bottom:8px; }
            .receipt__payment { font-size:13px; color:#555; margin-bottom:4px; }
            .receipt__payment span { font-weight:600; color:#1a1a1a; }
            .receipt__payment .label { color:#888; }
            .receipt__footer { text-align:center; border-top:2px dashed #ddd; padding-top:16px; margin-top:20px; font-size:12px; color:#888; }
            .receipt__footer strong { color:#c8a44e; }
            @media print {
              body { background:#fff; padding:0; }
              .no-print { display:none !important; }
              .receipt { box-shadow:none; border-radius:0; max-width:100%; padding:20px 16px; }
            }
          </style>
        </head>
        <body>

        <div class="no-print">
          <button class="btn btn--gold" onclick="window.print()">Print</button>
          <a class="btn btn--outline" href="/cashier/bookings.php">Back to bookings</a>
        </div>

        <div class="receipt">
          <div class="receipt__header">
            <div class="receipt__brand"><?= e(BRAND_NAME) ?></div>
            <div class="receipt__tagline"><?= e(BRAND_TAGLINE) ?></div>
            <div class="receipt__title">Booking Receipt</div>
          </div>

          <dl class="receipt__meta">
            <dt>Booking #</dt><dd><?= e($shortId) ?></dd>
            <dt>Date</dt><dd><?= $createdAt ?></dd>
            <dt>Created by</dt><dd><?= e($createdBy) ?></dd>
            <dt>Customer</dt><dd><?= e($customerName) ?></dd>
            <dt>Contact</dt><dd><?= e($contact) ?></dd>
            <?php if ($address !== '—'): ?>
            <dt>Address</dt><dd><?= e($address) ?></dd>
            <?php endif; ?>
            <dt>Appointment</dt><dd><?= $apptTime ?></dd>
            <dt>Return by</dt><dd><?= $retTime ?></dd>
          </dl>

          <table class="receipt__items">
            <thead>
              <tr>
                <th>Item</th>
                <th class="num">Qty</th>
                <th class="num">Price</th>
                <th class="num">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php $i = 0; foreach ($items as $it): $i++; ?>
                <tr>
                  <td class="name"><?= e($it['name'] ?? 'Item ' . $i) ?></td>
                  <td class="num"><?= (int)($it['qty'] ?? 0) ?></td>
                  <td class="num"><?= e(money((float)($it['price'] ?? 0))) ?></td>
                  <td class="num"><?= e(money((float)($it['subtotal'] ?? 0))) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div class="receipt__total">
            <span>Total</span>
            <span><?= e(money($total)) ?></span>
          </div>

          <div class="receipt__payment">
            <span class="label">Payment:</span>
            <span><?= $paymentMethod === 'gcash' ? 'GCash' : 'Counter' ?></span>
          </div>

          <div class="receipt__footer">
            Thank you for renting with us!<br>
            <strong><?= e(BRAND_NAME) ?></strong>
          </div>
        </div>

        </body>
        </html>
        <?php
    }
}
