<?php
/**
 * Project & Promo Submission Handler
 * Backend: Adams+ Digital Solutions
 * Handles: Main Project Form AND Side Banner Promo
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. SETUP
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

try {
    // --- CONFIGURATION ---
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

    // --- DATA SANITIZATION & LOGIC INTEGRATION ---
    $d = array_map('trim', $_POST);
    
    // 1. Basic Fields
    $firstName = htmlspecialchars($d['first-name'] ?? '');
    $lastName  = htmlspecialchars($d['last-name'] ?? '');
    $email     = filter_var($d['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $phone     = htmlspecialchars($d['phone'] ?? 'N/A');
    
    // 2. Logic: Handle "Promo" specific defaults
    // If the Promo Form sends data, 'company' might be missing, so we default to 'Personal/Promo'
    $company   = htmlspecialchars($d['company'] ?? 'N/A');
    
    $budget    = htmlspecialchars($d['budget'] ?? 'Not specified');
    $timeline  = htmlspecialchars($d['timeline'] ?? 'Flexible');
    $plan      = htmlspecialchars($d['plan'] ?? 'General Inquiry');
    $message   = htmlspecialchars($d['message'] ?? 'No additional details.');
    
    // 3. Metadata
    $refID     = strtoupper(uniqid('REF-'));
    $date      = date('M j, Y \a\t g:i A');

    // 4. Smart Name Formatting
    // If it's a Promo Claim, the JS sends "(Promo Claim)" as last name. 
    // We clean this up for the display name.
    $fullName = $firstName . ' ' . $lastName;

    // --- COMPONENT: LOGO (Reused) ---
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
    $adminEmailBody = <<<HTML
    <!DOCTYPE html>
    <html>
    <body style="margin: 0; padding: 20px; background-color: {$bodyBg}; {$fonts}">
        <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
            
            <div style="background: {$darkColor}; padding: 30px; text-align: center;">
                {$logoHTML}
                <div style="margin-top: 15px; color: #a1a1aa; font-size: 11px; text-transform: uppercase; letter-spacing: 2px; font-weight: 700;">Action Required</div>
            </div>

            <div style="padding: 40px;">
                
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
                    <span style="display: block; font-size: 10px; color: {$brandColor}; text-transform: uppercase; font-weight: 800; margin-bottom: 5px;">Category</span>
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

                    <span style="display: block; font-size: 11px; color: #71717a; text-transform: uppercase; font-weight: 700; margin-bottom: 8px;">Notes</span>
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

    // --- EMAIL 2: CUSTOMER RECEIPT ---
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
                    We have successfully received your submission for: <br>
                    <strong style="color: {$brandColor};">{$plan}</strong>.
                    <br><br>
                    Our team is currently reviewing your details. We will be in touch within <strong>24 hours</strong>.
                </p>

                <div style="text-align: left; background: #fafafa; border: 1px solid #e4e4e7; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
                    <div style="font-size: 11px; text-transform: uppercase; color: #a1a1aa; font-weight: 700; border-bottom: 1px solid #e4e4e7; padding-bottom: 10px; margin-bottom: 10px;">Submission Summary</div>
                    <div style="font-size: 14px; color: #52525b; margin-bottom: 6px;"><strong>Budget:</strong> {$budget}</div>
                    <div style="font-size: 14px; color: #52525b; margin-bottom: 6px;"><strong>Timeline:</strong> {$timeline}</div>
                    <div style="font-size: 14px; color: #52525b;"><strong>Ref ID:</strong> {$refID}</div>
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
    
    // FIRST: Send admin email
    $mail1 = new PHPMailer(true);
    
    // Server Settings
    $mail1->isSMTP();
    $mail1->Host       = $smtpConf['host'];
    $mail1->SMTPAuth   = true;
    $mail1->Username   = $smtpConf['user'];
    $mail1->Password   = $smtpConf['pass'];
    $mail1->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail1->Port       = $smtpConf['port'];
    $mail1->Timeout    = 20;
    
    // Anti-spam settings
    $mail1->CharSet = 'UTF-8';
    $mail1->addCustomHeader('X-Priority', '1');
    $mail1->addCustomHeader('X-Mailer', 'Adams+ Mailer');
    $mail1->Priority = 1;

    // SMART SUBJECT LINE
    if (strpos($plan, 'PROMO') !== false || strpos($lastName, '(Promo') !== false) {
        $subjectLine = "🎁 PROMO CLAIM: $firstName ($plan)";
    } else {
        $subjectLine = "🚀 New Lead: $plan ($firstName $lastName)";
    }

    // Send Admin Email
    $mail1->setFrom($smtpConf['user'], 'Adams+ Website');
    $mail1->addAddress('sales@adamsplusx.com'); // Admin
    $mail1->addReplyTo($email, $fullName);
    
    $mail1->isHTML(true);
    $mail1->Subject = $subjectLine;
    $mail1->Body    = $adminEmailBody;
    $mail1->AltBody = "New Inquiry: $plan from $fullName\nEmail: $email\nPhone: $phone\nBudget: $budget\nTimeline: $timeline\nMessage: $message";
    
    $adminSent = $mail1->send();

    // SECOND: Send client email (separate instance)
    $mail2 = new PHPMailer(true);
    
    // Server Settings for client email
    $mail2->isSMTP();
    $mail2->Host       = $smtpConf['host'];
    $mail2->SMTPAuth   = true;
    $mail2->Username   = $smtpConf['user'];
    $mail2->Password   = $smtpConf['pass'];
    $mail2->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail2->Port       = $smtpConf['port'];
    $mail2->Timeout    = 20;
    
    // Anti-spam settings for client email
    $mail2->CharSet = 'UTF-8';
    $mail2->addCustomHeader('X-Priority', '3');
    $mail2->addCustomHeader('X-Mailer', 'Adams+ Auto-Responder');
    $mail2->Priority = 3;

    // Send Client Email
    $mail2->setFrom($smtpConf['user'], 'Adams+ Digital Solutions');
    $mail2->addAddress($email);
    $mail2->addReplyTo('sales@adamsplusx.com', 'Adams+ Support');
    
    $mail2->isHTML(true);
    $mail2->Subject = "We received your request ($refID)";
    $mail2->Body    = $clientEmailBody;
    $mail2->AltBody = "Hi $firstName,\n\nThank you for your request about $plan.\n\nWe have received your submission and our team will contact you within 24 hours.\n\nReference ID: $refID\n\nBest regards,\nAdams+ Digital Solutions";
    
    $clientSent = $mail2->send();

    // Check if at least one email was sent successfully
    if ($adminSent || $clientSent) {
        $response['success'] = true;
        if (!$adminSent) {
            $response['message'] = 'Confirmation sent to your email. Our team will contact you soon.';
            error_log("Admin email failed but client email sent to: $email");
        } elseif (!$clientSent) {
            $response['message'] = 'Inquiry received by our team. You will be contacted shortly.';
            error_log("Client email failed but admin email sent to: sales@adamsplusx.com");
        } else {
            $response['message'] = 'Inquiry sent successfully.';
        }
    } else {
        throw new Exception("Failed to send emails.");
    }

} catch (Exception $e) {
    error_log("Mail Error: " . $e->getMessage());
    $response['message'] = 'Unable to send email. Please try again or contact us directly.';
} catch (\Throwable $t) {
    error_log("System Error: " . $t->getMessage());
    $response['message'] = 'System error. Please try again later.';
}

// Flush buffer and output
ob_end_clean();
echo json_encode($response);
?>