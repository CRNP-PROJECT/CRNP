<?php
/**
 * mailer.php — email delivery for OTPs and receipts.
 * Thin wrappers delegating to the App\Mail\Mailer class (PHPMailer/SMTP).
 */

use App\Mail\Mailer;

/**
 * Send a 6-digit OTP verification email. Returns true on success.
 */
function sendOTP(string $email, string $otp): bool {
    return Mailer::sendOtp($email, $otp);
}

/**
 * Generic mailer for receipts / notifications.
 */
function sendMail(string $to, string $subject, string $htmlBody, string $altBody = ''): bool {
    return Mailer::send($to, $subject, $htmlBody, $altBody);
}

/**
 * Send an order confirmation / receipt email.
 */
function sendOrderReceipt(string $email, array $order): bool {
    return Mailer::sendOrderReceipt($email, $order);
}

/**
 * Send a booking confirmation / receipt email.
 */
function sendBookingReceipt(string $email, array $booking): bool {
    return Mailer::sendBookingReceipt($email, $booking);
}
