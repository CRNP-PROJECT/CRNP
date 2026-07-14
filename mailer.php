<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

function sendNotification($toEmail, $subject, $message) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        // 🔴 CHANGE THIS
        $mail->Username = 'xian.mainz@gmail.com';
        $mail->Password = 'etvd wfid pjod zrmq';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('xian.mainz@gmail.com', 'CRNP System');
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Mail Error: " . $e->getMessage());
        return false;
    }
}