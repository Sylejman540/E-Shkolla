<?php
date_default_timezone_set('Europe/Tirane');
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


function sendSchoolEmail(array $emails, string $subject, string $htmlBody): bool
{
    $required = ['SMTP_HOST', 'SMTP_USER', 'SMTP_PASS', 'SMTP_PORT'];

    foreach ($required as $key) {
        if (empty($_ENV[$key])) {
            error_log("MAIL CONFIG ERROR: Missing {$key}");
            return false;
        }
    }

    if (empty($emails)) {
        error_log('MAIL DEBUG: empty email list');
        return false;
    }

    try {
        $mail = new PHPMailer(true);

        // 🔥 TURN ON DEBUG (TEMPORARY)
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function ($str) {
            error_log("SMTP DEBUG: " . $str);
        };

        $mail->isSMTP();
        $mail->Host       = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['SMTP_USER'];
        $mail->Password   = $_ENV['SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) $_ENV['SMTP_PORT'];

        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->setFrom($_ENV['SMTP_USER'], 'E-Shkolla');

        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail->addAddress($email);
            }
        }

        if (!$mail->getToAddresses()) {
            error_log('MAIL DEBUG: No valid recipients');
            return false;
        }

        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('MAIL EXCEPTION: ' . $mail->ErrorInfo);
        return false;
    }
}
