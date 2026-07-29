<?php
/**
 * mailer.php — PHPMailer configured for Gmail SMTP (STARTTLS, port 587).
 * Exposes sendOTP($email, $otp).
 */
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send a 6-digit OTP verification email. Returns true on success.
 */
function sendOTP(string $email, string $otp): bool {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // Recipients
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($email);
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your ' . BRAND_NAME . ' verification code';
        $mail->Body    = otp_email_html($otp);
        $mail->AltBody = "Your " . BRAND_NAME . " verification code is: " . $otp . "\nThis code expires in 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[mailer] OTP send failed to ' . $email . ': ' . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Generic mailer for receipts / notifications.
 */
function sendMail(string $to, string $subject, string $htmlBody, string $altBody = ''): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody ?: strip_tags($htmlBody);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[mailer] sendMail failed to ' . $to . ': ' . $mail->ErrorInfo);
        return false;
    }
}

function otp_email_html(string $otp): string {
    $brand   = BRAND_NAME;
    $tagline = BRAND_TAGLINE;
    return <<<HTML
<!doctype html><html><body style="margin:0;background:#f6f2ea;font-family:Georgia,'Times New Roman',serif;">
  <div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #e6dfd1;border-radius:14px;overflow:hidden;">
    <div style="background:#211b14;color:#f6f2ea;padding:28px 32px;">
      <div style="font-size:13px;letter-spacing:.22em;text-transform:uppercase;color:#c8a45c;">{$brand}</div>
      <div style="font-size:22px;margin-top:6px;">Email verification</div>
    </div>
    <div style="padding:32px;color:#211b14;">
      <p style="margin:0 0 14px;">Please use the code below to verify your email address. It expires in 10 minutes.</p>
      <div style="text-align:center;margin:26px 0;">
        <span style="display:inline-block;font-family:'Courier New',monospace;font-size:34px;letter-spacing:.5em;color:#211b14;background:#f6f2ea;border:1px dashed #c8a45c;border-radius:12px;padding:16px 22px 16px 28px;">{$otp}</span>
      </div>
      <p style="margin:0;color:#8a7f70;font-size:13px;">If you did not create an account, you can safely ignore this email.</p>
    </div>
    <div style="background:#fbf8f2;color:#8a7f70;font-size:12px;padding:16px 32px;text-align:center;">&copy; {$brand} &middot; {$tagline}</div>
  </div>
</body></html>
HTML;
}

/**
 * Send an order confirmation / receipt email.
 *
 * $order keys: id, items (map of {name,qty,price,subtotal}), total,
 * full_name, payment_method ('gcash' | 'counter'), payment_status,
 * created_at, pickup_time (optional).
 *
 * Visually consistent with otp_email_html() — dark header, gold accent,
 * warm cream body. Returns true on success, false on failure.
 */
function sendOrderReceipt(string $email, array $order): bool {
    $brand   = BRAND_NAME;
    $tagline = BRAND_TAGLINE;

    $orderId     = (string) ($order['id'] ?? '');
    $shortId     = $orderId !== '' ? substr($orderId, 0, 8) : '—';
    $fullName    = (string) ($order['full_name'] ?? '');
    $total       = (float)  ($order['total'] ?? 0);
    $method      = (string) ($order['payment_method'] ?? 'counter');
    $payStatus   = (string) ($order['payment_status'] ?? '');
    $createdAt   = (string) ($order['created_at'] ?? '');
    $pickupTime  = (string) ($order['pickup_time'] ?? '');
    $items       = is_array($order['items'] ?? null) ? $order['items'] : [];

    /* Payment line: "Payment: GCash — verifying" or "Payment: Pay at counter". */
    if ($method === 'gcash') {
        $payLine = 'GCash — ' . ($payStatus === 'paid' ? 'paid' : 'verifying');
    } else {
        $payLine = 'Pay at counter';
    }

    /* Items table rows. */
    $rowsHtml = '';
    foreach ($items as $row) {
        if (!is_array($row)) continue;
        $name      = htmlspecialchars((string) ($row['name'] ?? 'Item'), ENT_QUOTES, 'UTF-8');
        $qty       = (int)    ($row['qty'] ?? 1);
        $price     = (float)  ($row['price'] ?? 0);
        $subtotal  = (float)  ($row['subtotal'] ?? ($price * $qty));
        $priceTxt    = "\u{20B1}" . number_format($price, 2);
        $subtotalTxt = "\u{20B1}" . number_format($subtotal, 2);
        $rowsHtml .= <<<HTML
          <tr>
            <td style="padding:10px 12px;border-bottom:1px solid #efe8da;color:#211b14;">{$name}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #efe8da;text-align:center;color:#211b14;">{$qty}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #efe8da;text-align:right;color:#211b14;">{$priceTxt}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #efe8da;text-align:right;color:#211b14;font-weight:600;">{$subtotalTxt}</td>
          </tr>
HTML;
    }
    if ($rowsHtml === '') {
        $rowsHtml = '<tr><td colspan="4" style="padding:14px 12px;color:#8a7f70;text-align:center;">No items recorded.</td></tr>';
    }

    $totalTxt   = "\u{20B1}" . number_format($total, 2);
    $placedTxt  = $createdAt !== '' ? date('M j, Y g:i A', strtotime($createdAt)) : '';
    $pickupLine = $pickupTime !== ''
        ? 'Pickup time: <strong style="color:#211b14;">' . htmlspecialchars($pickupTime, ENT_QUOTES, 'UTF-8') . '</strong><br>'
        : '';
    $greeting   = $fullName !== '' ? 'Hi ' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . ',' : 'Hi there,';

    $subject = 'Your ' . $brand . ' order #' . $shortId;

    $html = <<<HTML
<!doctype html><html><body style="margin:0;background:#f6f2ea;font-family:Georgia,'Times New Roman',serif;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e6dfd1;border-radius:14px;overflow:hidden;">
    <div style="background:#211b14;color:#f6f2ea;padding:28px 32px;">
      <div style="font-size:13px;letter-spacing:.22em;text-transform:uppercase;color:#c8a45c;">{$brand}</div>
      <div style="font-size:22px;margin-top:6px;">Order confirmation</div>
    </div>
    <div style="padding:32px;color:#211b14;">
      <p style="margin:0 0 6px;">{$greeting}</p>
      <p style="margin:0 0 18px;color:#5a4f42;">Thanks for your order! Here's your receipt.</p>

      <div style="background:#fbf8f2;border:1px solid #efe8da;border-radius:10px;padding:14px 16px;margin-bottom:22px;">
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#8a7f70;margin-bottom:4px;">
          <span>Order number</span><span style="color:#211b14;font-weight:600;">#{$shortId}</span>
        </div>
        {$pickupLine}
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#8a7f70;">
          <span>Placed</span><span style="color:#211b14;">{$placedTxt}</span>
        </div>
      </div>

      <table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px;">
        <thead>
          <tr style="background:#211b14;color:#f6f2ea;">
            <th style="padding:12px;text-align:left;font-weight:600;letter-spacing:.04em;">Item</th>
            <th style="padding:12px;text-align:center;font-weight:600;letter-spacing:.04em;">Qty</th>
            <th style="padding:12px;text-align:right;font-weight:600;letter-spacing:.04em;">Unit</th>
            <th style="padding:12px;text-align:right;font-weight:600;letter-spacing:.04em;">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          {$rowsHtml}
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" style="padding:14px 12px;text-align:right;text-transform:uppercase;font-size:12px;letter-spacing:.08em;color:#8a7f70;">Total</td>
            <td style="padding:14px 12px;text-align:right;font-size:18px;font-weight:700;color:#211b14;">{$totalTxt}</td>
          </tr>
        </tfoot>
      </table>

      <div style="margin-top:18px;padding:14px 16px;background:#fbf8f2;border-left:3px solid #c8a45c;border-radius:8px;font-size:14px;color:#211b14;">
        <strong>Payment:</strong> {$payLine}
      </div>

      <p style="margin:18px 0 6px;color:#211b14;">Please present this confirmation at the counter.</p>
      <p style="margin:0;color:#8a7f70;font-size:13px;">We'll notify you when your order is ready for pickup.</p>
    </div>
    <div style="background:#fbf8f2;color:#8a7f70;font-size:12px;padding:16px 32px;text-align:center;">&copy; {$brand} &middot; {$tagline}</div>
  </div>
</body></html>
HTML;

    $alt = "Your " . $brand . " order #" . $shortId . "\n"
         . "Total: " . $totalTxt . "\n"
         . "Payment: " . $payLine . "\n\n"
         . "Please present this confirmation at the counter.\n"
         . "We'll notify you when your order is ready for pickup.";

    return sendMail($email, $subject, $html, $alt);
}

/**
 * Send a booking confirmation / receipt email.
 *
 * $booking keys: id, items (map of {name,qty,price,subtotal}), total,
 * full_name, payment_method ('gcash' | 'counter'), payment_status,
 * appointment_time, return_time, created_at, contact, address.
 */
function sendBookingReceipt(string $email, array $booking): bool {
    $brand   = BRAND_NAME;
    $tagline = BRAND_TAGLINE;

    $bookingId = (string) ($booking['id'] ?? '');
    $shortId   = $bookingId !== '' ? substr($bookingId, 0, 8) : '—';
    $fullName  = (string) ($booking['full_name'] ?? $booking['user_name'] ?? '');
    $total     = (float)  ($booking['total'] ?? 0);
    $method    = (string) ($booking['payment_method'] ?? 'counter');
    $payStatus = (string) ($booking['payment_status'] ?? '');
    $apptTime  = (string) ($booking['appointment_time'] ?? '');
    $retTime   = (string) ($booking['return_time'] ?? '');
    $contact   = (string) ($booking['contact'] ?? '');
    $address   = (string) ($booking['address'] ?? '');
    $createdAt = (string) ($booking['created_at'] ?? '');
    $items     = is_array($booking['items'] ?? null) ? $booking['items'] : [];

    if ($method === 'gcash') {
        $payLine = 'GCash — ' . ($payStatus === 'paid' ? 'paid' : 'verifying');
    } else {
        $payLine = 'Pay at counter';
    }

    $rowsHtml = '';
    foreach ($items as $row) {
        if (!is_array($row)) continue;
        $name      = htmlspecialchars((string) ($row['name'] ?? 'Item'), ENT_QUOTES, 'UTF-8');
        $qty       = (int)    ($row['qty'] ?? 1);
        $price     = (float)  ($row['price'] ?? 0);
        $subtotal  = (float)  ($row['subtotal'] ?? ($price * $qty));
        $priceTxt    = "\u{20B1}" . number_format($price, 2);
        $subtotalTxt = "\u{20B1}" . number_format($subtotal, 2);
        $rowsHtml .= <<<HTML
          <tr>
            <td style="padding:10px 12px;border-bottom:1px solid #efe8da;color:#211b14;">{$name}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #efe8da;text-align:center;color:#211b14;">{$qty}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #efe8da;text-align:right;color:#211b14;">{$priceTxt}</td>
            <td style="padding:10px 12px;border-bottom:1px solid #efe8da;text-align:right;color:#211b14;font-weight:600;">{$subtotalTxt}</td>
          </tr>
HTML;
    }
    if ($rowsHtml === '') {
        $rowsHtml = '<tr><td colspan="4" style="padding:14px 12px;color:#8a7f70;text-align:center;">No items recorded.</td></tr>';
    }

    $totalTxt   = "\u{20B1}" . number_format($total, 2);
    $placedTxt  = $createdAt !== '' ? date('M j, Y g:i A', strtotime($createdAt)) : '';
    $apptLine   = $apptTime !== '' ? 'Appointment: <strong style="color:#211b14;">' . htmlspecialchars($apptTime, ENT_QUOTES, 'UTF-8') . '</strong><br>' : '';
    $returnLine = $retTime !== '' ? 'Return by: <strong style="color:#211b14;">' . htmlspecialchars($retTime, ENT_QUOTES, 'UTF-8') . '</strong><br>' : '';
    $greeting   = $fullName !== '' ? 'Hi ' . htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') . ',' : 'Hi there,';

    $subject = 'Your ' . $brand . ' booking #' . $shortId;

    $html = <<<HTML
<!doctype html><html><body style="margin:0;background:#f6f2ea;font-family:Georgia,'Times New Roman',serif;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e6dfd1;border-radius:14px;overflow:hidden;">
    <div style="background:#211b14;color:#f6f2ea;padding:28px 32px;">
      <div style="font-size:13px;letter-spacing:.22em;text-transform:uppercase;color:#c8a45c;">{$brand}</div>
      <div style="font-size:22px;margin-top:6px;">Booking confirmation</div>
    </div>
    <div style="padding:32px;color:#211b14;">
      <p style="margin:0 0 6px;">{$greeting}</p>
      <p style="margin:0 0 18px;color:#5a4f42;">Thanks for your rental booking! Here's your receipt.</p>

      <div style="background:#fbf8f2;border:1px solid #efe8da;border-radius:10px;padding:14px 16px;margin-bottom:22px;">
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#8a7f70;margin-bottom:4px;">
          <span>Booking number</span><span style="color:#211b14;font-weight:600;">#{$shortId}</span>
        </div>
        {$apptLine}
        {$returnLine}
        <div style="display:flex;justify-content:space-between;font-size:13px;color:#8a7f70;">
          <span>Booked</span><span style="color:#211b14;">{$placedTxt}</span>
        </div>
      </div>

      <table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px;">
        <thead>
          <tr style="background:#211b14;color:#f6f2ea;">
            <th style="padding:12px;text-align:left;font-weight:600;letter-spacing:.04em;">Item</th>
            <th style="padding:12px;text-align:center;font-weight:600;letter-spacing:.04em;">Qty</th>
            <th style="padding:12px;text-align:right;font-weight:600;letter-spacing:.04em;">Unit</th>
            <th style="padding:12px;text-align:right;font-weight:600;letter-spacing:.04em;">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          {$rowsHtml}
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" style="padding:14px 12px;text-align:right;text-transform:uppercase;font-size:12px;letter-spacing:.08em;color:#8a7f70;">Total</td>
            <td style="padding:14px 12px;text-align:right;font-size:18px;font-weight:700;color:#211b14;">{$totalTxt}</td>
          </tr>
        </tfoot>
      </table>

      <div style="margin-top:18px;padding:14px 16px;background:#fbf8f2;border-left:3px solid #c8a45c;border-radius:8px;font-size:14px;color:#211b14;">
        <strong>Payment:</strong> {$payLine}
      </div>

      <p style="margin:18px 0 6px;color:#211b14;">Please present this confirmation when picking up your rental items.</p>
      <p style="margin:0;color:#8a7f70;font-size:13px;">We will confirm your booking shortly.</p>
    </div>
    <div style="background:#fbf8f2;color:#8a7f70;font-size:12px;padding:16px 32px;text-align:center;">&copy; {$brand} &middot; {$tagline}</div>
  </div>
</body></html>
HTML;

    $alt = "Your " . $brand . " booking #" . $shortId . "\n"
         . "Total: " . $totalTxt . "\n"
         . "Payment: " . $payLine . "\n\n"
         . "Please present this confirmation when picking up your rental items.\n"
         . "We will confirm your booking shortly.";

    return sendMail($email, $subject, $html, $alt);
}
