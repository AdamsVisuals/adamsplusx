<?php
// submit_newsletter.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. SETUP
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

$response = ['success' => false, 'message' => 'An error occurred'];

try {
    // 2. CHECK DEPENDENCIES
    if (!file_exists(__DIR__ . '/PHPMailer-master/src/PHPMailer.php')) {
        throw new Exception("PHPMailer missing.");
    }

    require __DIR__ . '/PHPMailer-master/src/Exception.php';
    require __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
    require __DIR__ . '/PHPMailer-master/src/SMTP.php';

    // 3. VALIDATE
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid Method");
    }

    // Bot check (Honeypot) - Check if you added the hidden field in HTML
    if (!empty($_POST['website_check'])) {
        echo json_encode(['success' => true, 'message' => 'Subscribed']); exit;
    }

    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid Email Format");
    }

    $date = date('M j, Y g:i A');

    // 4. EMAIL STYLES & LOGO
    $brandColor = '#2563eb';
    $darkColor  = '#09090b';
    
    // The Circular Logo HTML
    $logoHTML = <<<HTML
    <table cellpadding="0" cellspacing="0" border="0" style="margin: 0 auto;">
        <tr>
            <td style="padding-right: 12px; vertical-align: middle;">
                <div style="width: 36px; height: 36px; background-color: {$brandColor}; border-radius: 50%; color: #ffffff; font-weight: 900; font-size: 13px; line-height: 36px; text-align: center; font-family: Arial, sans-serif;">A+</div>
            </td>
            <td style="vertical-align: middle;">
                <span style="font-size: 20px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; font-family: Arial, sans-serif;">Adams</span><span style="font-size: 20px; font-weight: 800; color: {$brandColor}; font-family: Arial, sans-serif;">+</span>
            </td>
        </tr>
    </table>
HTML;

    // 5. EMAIL TEMPLATES

    // Email to USER (Welcome)
    $userBody = <<<HTML
    <!DOCTYPE html>
    <html>
    <body style="margin: 0; padding: 20px; background-color: #f4f4f5; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
        <div style="max-width: 500px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
            <div style="background: {$darkColor}; padding: 30px; text-align: center;">
                {$logoHTML}
            </div>
            <div style="padding: 40px; text-align: center;">
                <h2 style="margin-top: 0; color: #18181b; font-size: 22px;">Welcome to the inner circle.</h2>
                <p style="color: #52525b; line-height: 1.6; font-size: 15px;">
                    Hi there, thanks for connecting with Adams+. <br><br>
                    You are now on our list to receive exclusive updates on digital strategy, design trends, and agency news. We promise to keep it valuable and spam-free.
                </p>
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #f4f4f5;">
                    <a href="https://adamsplusx.com" style="color: {$brandColor}; font-weight: bold; text-decoration: none; font-size: 14px;">Visit Website &rarr;</a>
                </div>
            </div>
        </div>
    </body>
    </html>
HTML;

    // Email to ADMIN (Notification)
    $adminBody = <<<HTML
    <div style="font-family: monospace; color: #333;">
        <h3>🔔 New Subscriber</h3>
        <p><strong>Email:</strong> {$email}</p>
        <p><strong>Time:</strong> {$date}</p>
        <p><strong>Source:</strong> Footer / Contact Section</p>
    </div>
HTML;

    // 6. SEND MAILS
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'hello@adamsplusx.com';
    $mail->Password   = 'Mihanda089564@';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Send to Admin
    $mail->setFrom('hello@adamsplusx.com', 'Adams+ Bot');
    $mail->addAddress('sales@adamsplusx.com');
    $mail->isHTML(true);
    $mail->Subject = "New Subscriber: $email";
    $mail->Body    = $adminBody;
    $mail->send();

    // Send to User
    $mail->clearAddresses();
    $mail->addAddress($email);
    $mail->setFrom('hello@adamsplusx.com', 'Adams+ Digital');
    $mail->Subject = "Welcome to Adams+";
    $mail->Body    = $userBody;
    $mail->send();

    $response['success'] = true;
    $response['message'] = 'Subscribed successfully.';

} catch (Exception $e) {
    error_log("Mail Error: " . $e->getMessage());
    $response['message'] = 'Mailer Error: ' . $e->getMessage();
} catch (\Throwable $t) {
    $response['message'] = 'System Error';
}

ob_end_clean();
echo json_encode($response);
?>