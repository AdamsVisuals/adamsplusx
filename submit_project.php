<?php
/**
 * Project Submission Handler
 * Backend: Adams+ Digital Solutions
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Strict Error Handling for Debugging (Turn off in production if needed)
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

// API Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Default Response
$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

try {
    // --- CONFIGURATION & STYLES ---
    // A developer would define these once to maintain consistency easily.
    $brandColor = '#2563eb'; // Electric Blue
    $darkColor  = '#09090b'; // Zinc 950
    $bodyBg     = '#f4f4f5'; // Zinc 100
    
    $fonts      = "font-family: 'Inter', system-ui, -apple-system, sans-serif;";
    
    // SMTP Credentials
    $smtpConf = [
        'host' => 'smtp.hostinger.com',
        'user' => 'hello@adamsplusx.com',
        'pass' => 'Mihanda089564@',
        'port' => 587
    ];

    // --- DEPENDENCY CHECK ---
    if (!file_exists(__DIR__ . '/PHPMailer-master/src/PHPMailer.php')) {
        throw new Exception("Server Configuration Error: Mailer library missing.");
    }

    require __DIR__ . '/PHPMailer-master/src/Exception.php';
    require __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
    require __DIR__ . '/PHPMailer-master/src/SMTP.php';

    // --- REQUEST VALIDATION ---
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception("Method Not Allowed");
    }

    // Honeypot (Bot Protection)
    if (!empty($_POST['website'])) {
        exit(json_encode(['success' => true, 'message' => 'Sent']));
    }

    // --- DATA SANITIZATION ---
    $d = array_map('trim', $_POST);
    
    $firstName = htmlspecialchars($d['first-name'] ?? '');
    $lastName  = htmlspecialchars($d['last-name'] ?? '');
    $fullName  = $firstName . ' ' . $lastName;
    $email     = filter_var($d['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $phone     = htmlspecialchars($d['phone'] ?? 'N/A');
    $company   = htmlspecialchars($d['company'] ?? 'N/A');
    $budget    = htmlspecialchars($d['budget'] ?? 'Not specified');
    $timeline  = htmlspecialchars($d['timeline'] ?? 'Flexible');
    $plan      = htmlspecialchars($d['plan'] ?? 'General Inquiry');
    $message   = htmlspecialchars($d['message'] ?? 'No additional details.');
    
    $refID     = strtoupper(uniqid('PROJ-'));
    $date      = date('M j, Y \a\t g:i A');

    // --- COMPONENT: LOGO (Reused) ---
    // A table-based layout ensures the circle aligns perfectly in Outlook/Gmail
    $logoHTML = <<<HTML
    <table cellpadding="0" cellspacing="0" border="0" style="margin: 0 auto;">
        <tr>
            <td style="padding-right: 12px; vertical-align: middle;">
                <div style="
                    width: 40px; 
                    height: 40px; 
                    background-color: {$brandColor}; 
                    border-radius: 50%; 
                    color: #ffffff; 
                    font-weight: 900; 
                    font-size: 14px; 
                    line-height: 40px; 
                    text-align: center;
                    font-family: Arial, sans-serif;">
                    A+
                </div>
            </td>
            <td style="vertical-align: middle;">
                <span style="font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; font-family: Arial, sans-serif;">Adams</span><span style="font-size: 24px; font-weight: 800; color: {$brandColor}; font-family: Arial, sans-serif;">+</span>
            </td>
        </tr>
    </table>
HTML;

    // --- EMAIL 1: INTERNAL BRIEF (To Your Team) ---
    // Designed for quick scanning of data.
    $adminEmailBody = <<<HTML
    <!DOCTYPE html>
    <html>
    <body style="margin: 0; padding: 20px; background-color: {$bodyBg}; {$fonts}">
        <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            
            <div style="background: {$darkColor}; padding: 30px; text-align: center;">
                {$logoHTML}
                <div style="margin-top: 15px; color: #a1a1aa; font-size: 11px; text-transform: uppercase; letter-spacing: 2px; font-weight: 700;">New Lead Generated</div>
            </div>

            <div style="padding: 40px;">
                
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
                    <span style="display: block; font-size: 10px; color: {$brandColor}; text-transform: uppercase; font-weight: 800; margin-bottom: 5px;">Interested In</span>
                    <strong style="font-size: 18px; color: #1e3a8a;">{$plan}</strong>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 30px;">
                    <div>
                        <span style="display: block; font-size: 11px; color: #71717a; text-transform: uppercase; font-weight: 700; margin-bottom: 5px;">Client</span>
                        <div style="font-size: 15px; color: #18181b; font-weight: 500;">{$fullName}</div>
                        <a href="mailto:{$email}" style="color: {$brandColor}; font-size: 14px; text-decoration: none;">{$email}</a>
                    </div>
                    <div>
                        <span style="display: block; font-size: 11px; color: #71717a; text-transform: uppercase; font-weight: 700; margin-bottom: 5px;">Details</span>
                        <div style="font-size: 14px; color: #18181b;">{$company}</div>
                        <div style="font-size: 14px; color: #18181b;">{$phone}</div>
                    </div>
                </div>

                <div style="border-top: 1px solid #e4e4e7; padding-top: 20px;">
                    <table width="100%" style="margin-bottom: 20px;">
                        <tr>
                            <td width="50%">
                                <span style="font-size: 11px; color: #71717a; text-transform: uppercase; font-weight: 700;">Budget</span><br>
                                <strong style="color: #18181b;">{$budget}</strong>
                            </td>
                            <td width="50%">
                                <span style="font-size: 11px; color: #71717a; text-transform: uppercase; font-weight: 700;">Timeline</span><br>
                                <strong style="color: #18181b;">{$timeline}</strong>
                            </td>
                        </tr>
                    </table>

                    <span style="display: block; font-size: 11px; color: #71717a; text-transform: uppercase; font-weight: 700; margin-bottom: 8px;">Vision Note</span>
                    <div style="background: #f4f4f5; padding: 16px; border-radius: 6px; font-size: 14px; line-height: 1.6; color: #3f3f46;">
                        "{$message}"
                    </div>
                </div>

            </div>
            
            <div style="background: #f4f4f5; padding: 12px; text-align: center; font-size: 10px; color: #a1a1aa;">
                REF: {$refID}
            </div>
        </div>
    </body>
    </html>
HTML;

    // --- EMAIL 2: CUSTOMER RECEIPT (Warm & Trust Building) ---
    // Designed to reassure them that the request was successful.
    $clientEmailBody = <<<HTML
    <!DOCTYPE html>
    <html>
    <body style="margin: 0; padding: 20px; background-color: {$bodyBg}; {$fonts}">
        <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
            
            <div style="background: {$darkColor}; padding: 40px 30px; text-align: center;">
                {$logoHTML}
                <h1 style="color: #fff; margin: 25px 0 0 0; font-size: 22px; font-weight: 600;">Request Received</h1>
            </div>

            <div style="padding: 40px; text-align: center;">
                <p style="font-size: 16px; color: #3f3f46; line-height: 1.6; margin-bottom: 30px;">
                    Hi <strong>{$firstName}</strong>, thanks for choosing Adams+. <br>
                    We've received your inquiry for the <strong style="color: {$brandColor};">{$plan}</strong>.
                    <br><br>
                    Our team is currently reviewing your project scope. You can expect to hear from us within <strong>24 hours</strong> to schedule a discovery session.
                </p>

                <div style="text-align: left; background: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
                    <div style="font-size: 11px; text-transform: uppercase; color: #a1a1aa; font-weight: 700; border-bottom: 1px solid #e4e4e7; padding-bottom: 10px; margin-bottom: 10px;">Submission Summary</div>
                    <div style="font-size: 14px; color: #52525b; margin-bottom: 6px;"><strong>Budget:</strong> {$budget}</div>
                    <div style="font-size: 14px; color: #52525b; margin-bottom: 6px;"><strong>Timeline:</strong> {$timeline}</div>
                    <div style="font-size: 14px; color: #52525b;"><strong>Reference ID:</strong> {$refID}</div>
                </div>

                <a href="https://adamsplusx.com" style="display: inline-block; background: {$darkColor}; color: #fff; text-decoration: none; padding: 14px 28px; border-radius: 6px; font-weight: 600; font-size: 14px;">Return to Website</a>
            </div>

            <div style="background: #f4f4f5; padding: 20px; text-align: center; border-top: 1px solid #e4e4e7;">
                <div style="font-size: 12px; color: #52525b; font-weight: 700;">Adams+ Digital Solutions</div>
                <div style="font-size: 12px; color: #a1a1aa;">Arusha, Tanzania</div>
            </div>
        </div>
    </body>
    </html>
HTML;


    // --- MAILER EXECUTION ---
    
    $mail = new PHPMailer(true);
    
    // Server Settings
    $mail->isSMTP();
    $mail->Host       = $smtpConf['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $smtpConf['user'];
    $mail->Password   = $smtpConf['pass'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = $smtpConf['port'];
    $mail->Timeout    = 20;

    // Send Admin Email
    $mail->setFrom($smtpConf['user'], 'Adams+ Website');
    $mail->addAddress('sales@adamsplusx.com'); // Admin
    $mail->addReplyTo($email, $fullName);
    
    $mail->isHTML(true);
    $mail->Subject = "🚀 New Lead: $plan ($fullName)";
    $mail->Body    = $adminEmailBody;
    $mail->AltBody = "New Project Inquiry from $fullName regarding $plan.";
    
    $mail->send();

    // Send Client Email
    $mail->clearAddresses();
    $mail->clearReplyTos();
    
    $mail->addAddress($email);
    $mail->setFrom($smtpConf['user'], 'Adams+ Digital Solutions');
    $mail->addReplyTo('sales@adamsplusx.com', 'Adams+ Support');
    
    $mail->Subject = "We received your request ($refID)";
    $mail->Body    = $clientEmailBody;
    
    $mail->send();

    // Final Success Output
    $response['success'] = true;
    $response['message'] = 'Inquiry sent successfully.';

} catch (Exception $e) {
    // Log internal error but return a generic message to user if basic mail failed
    error_log("Mail Error: " . $e->getMessage());
    $response['message'] = 'Mailer Error: ' . $e->getMessage();
} catch (\Throwable $t) {
    error_log("System Error: " . $t->getMessage());
    $response['message'] = 'System Error: ' . $t->getMessage();
}

// Flush buffer and output
ob_end_clean();
echo json_encode($response);
?>