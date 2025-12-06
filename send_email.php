<?php
// send_email.php - Hostinger Configuration
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// HOSTINGER SPECIFIC CONFIGURATION
// =================================
// Option 1: Using Hostinger's SMTP (Recommended for better deliverability)
$config = [
    'smtp_host' => 'smtp.hostinger.com',    // Hostinger's SMTP server
    'smtp_port' => 465,                     // 587 for TLS, 465 for SSL
    'smtp_username' => 'hello@adamsplusx.com', // Your Hostinger email
    'smtp_password' => 'Mihanda895@',  // Your email account password
    'smtp_secure' => 'tls',                 // 'tls' or 'ssl'
    'from_email' => 'hello@adamsplusx.com',
    'from_name' => 'Adams+ Digital Solutions',
    'to_email' => 'hello@adamsplusx.com',   // Where to send inquiries
    'to_name' => 'Adams+ Team',
    'reply_to' => true,                     // Set reply-to to customer email
];

// Option 2: Using PHP mail() function (if SMTP doesn't work)
$use_php_mail = false; // Set to true to use PHP mail() instead of SMTP

// =================================

// Response function
function sendResponse($success, $message, $data = []) {
    http_response_code($success ? 200 : 400);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method.');
}

// Get and sanitize input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST; // Fallback to form data
}

// Required fields
$required = ['firstName', 'lastName', 'email', 'plan'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        sendResponse(false, "Missing required field: $field");
    }
}

// Sanitize inputs
$firstName = htmlspecialchars(trim($input['firstName']), ENT_QUOTES, 'UTF-8');
$lastName = htmlspecialchars(trim($input['lastName']), ENT_QUOTES, 'UTF-8');
$email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
$plan = htmlspecialchars(trim($input['plan']), ENT_QUOTES, 'UTF-8');
$message = isset($input['message']) ? htmlspecialchars(trim($input['message']), ENT_QUOTES, 'UTF-8') : '';

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Invalid email address.');
}

// Validate message length
if (strlen($message) > 2000) {
    sendResponse(false, 'Message is too long. Maximum 2000 characters allowed.');
}

// Generate unique reference
$reference = 'ADM-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));

try {
    if ($use_php_mail) {
        // OPTION 1: Use PHP mail() function (Hostinger default)
        // ======================================================
        $to = $config['to_email'];
        $subject = "New Project Inquiry: $plan - Adams+ Digital Solutions";
        
        // Email headers
        $headers = "From: {$config['from_name']} <{$config['from_email']}>\r\n";
        $headers .= "Reply-To: $firstName $lastName <$email>\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        $headers .= "X-Priority: 1 (Highest)\r\n";
        
        // Email body (HTML)
        $body = createEmailBody($firstName, $lastName, $email, $plan, $message, $reference);
        
        // Send email
        if (mail($to, $subject, $body, $headers)) {
            // Send confirmation to client
            $confirmationSubject = "Thank You for Contacting Adams+ Digital Solutions";
            $confirmationBody = createConfirmationBody($firstName, $plan, $reference);
            
            $confirmationHeaders = "From: {$config['from_name']} <{$config['from_email']}>\r\n";
            $confirmationHeaders .= "MIME-Version: 1.0\r\n";
            $confirmationHeaders .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            // Don't block on confirmation email
            @mail($email, $confirmationSubject, $confirmationBody, $confirmationHeaders);
            
            sendResponse(true, 'Thank you! Your inquiry has been sent successfully. We will contact you within 24 hours.', [
                'reference' => $reference
            ]);
        } else {
            throw new Exception('Failed to send email using PHP mail() function.');
        }
        
    } else {
        // OPTION 2: Use SMTP (Recommended for Hostinger)
        // ==============================================
        $mail = new PHPMailer(true);
        
        // Server settings for Hostinger SMTP
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_secure'];
        $mail->Port = $config['smtp_port'];
        
        // Optional: Debug mode (enable for testing)
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        
        // Character encoding
        $mail->CharSet = 'UTF-8';
        
        // Recipients
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($config['to_email'], $config['to_name']);
        
        if ($config['reply_to']) {
            $mail->addReplyTo($email, $firstName . ' ' . $lastName);
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "New Project Inquiry: $plan - Adams+ Digital Solutions";
        $mail->Body = createEmailBody($firstName, $lastName, $email, $plan, $message, $reference);
        $mail->AltBody = createPlainTextBody($firstName, $lastName, $email, $plan, $message, $reference);
        
        // Send email to company
        if ($mail->send()) {
            // Send confirmation to client
            try {
                $confirmationMail = new PHPMailer(true);
                $confirmationMail->isSMTP();
                $confirmationMail->Host = $config['smtp_host'];
                $confirmationMail->SMTPAuth = true;
                $confirmationMail->Username = $config['smtp_username'];
                $confirmationMail->Password = $config['smtp_password'];
                $confirmationMail->SMTPSecure = $config['smtp_secure'];
                $confirmationMail->Port = $config['smtp_port'];
                
                $confirmationMail->setFrom($config['from_email'], $config['from_name']);
                $confirmationMail->addAddress($email, $firstName . ' ' . $lastName);
                
                $confirmationMail->isHTML(true);
                $confirmationMail->Subject = "Thank You for Contacting Adams+ Digital Solutions";
                $confirmationMail->Body = createConfirmationBody($firstName, $plan, $reference);
                $confirmationMail->AltBody = createPlainConfirmationBody($firstName, $plan, $reference);
                
                $confirmationMail->send();
            } catch (Exception $e) {
                // Log but don't fail if confirmation email fails
                error_log("Confirmation email failed: " . $e->getMessage());
            }
            
            sendResponse(true, 'Thank you! Your inquiry has been sent successfully. We will contact you within 24 hours.', [
                'reference' => $reference
            ]);
            
        } else {
            throw new Exception('SMTP mail() failed to send.');
        }
    }
    
} catch (Exception $e) {
    error_log("Email error: " . $e->getMessage());
    
    // Fallback to simple mail() if SMTP fails
    try {
        $to = $config['to_email'];
        $subject = "New Project Inquiry: $plan - Adams+ Digital Solutions";
        $headers = "From: {$config['from_name']} <{$config['from_email']}>\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body = createSimpleEmailBody($firstName, $lastName, $email, $plan, $message, $reference);
        
        if (@mail($to, $subject, $body, $headers)) {
            sendResponse(true, 'Thank you! Your inquiry has been sent successfully. We will contact you within 24 hours.', [
                'reference' => $reference
            ]);
        } else {
            throw new Exception('All email methods failed.');
        }
    } catch (Exception $fallbackError) {
        sendResponse(false, 'An error occurred while sending your message. Please try again later or contact us directly.');
    }
}

// Helper functions for email content
function createEmailBody($firstName, $lastName, $email, $plan, $message, $reference) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; }
            .header { background: #2563eb; color: white; padding: 30px 20px; text-align: center; }
            .content { padding: 30px; background: #f9fafb; }
            .field { margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb; }
            .field:last-child { border-bottom: none; }
            .label { font-weight: bold; color: #2563eb; display: block; margin-bottom: 5px; font-size: 14px; }
            .value { color: #374151; font-size: 15px; }
            .message-box { background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #2563eb; }
            .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; }
            .reference { background: #dbeafe; padding: 10px; border-radius: 6px; text-align: center; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0; font-size: 24px;'>New Project Inquiry</h1>
                <p style='margin: 10px 0 0; opacity: 0.9;'>Adams+ Digital Solutions</p>
            </div>
            
            <div class='content'>
                <div class='reference'>
                    <strong>Reference:</strong> $reference
                </div>
                
                <div class='field'>
                    <span class='label'>Client Information</span>
                    <div class='value'>
                        <strong>$firstName $lastName</strong><br>
                        <a href='mailto:$email' style='color: #2563eb;'>$email</a>
                    </div>
                </div>
                
                <div class='field'>
                    <span class='label'>Selected Package</span>
                    <div class='value' style='background: #dcfce7; padding: 10px; border-radius: 6px;'>
                        <strong>$plan</strong>
                    </div>
                </div>
                
                <div class='field'>
                    <span class='label'>Project Details</span>
                    <div class='message-box'>" . nl2br(htmlspecialchars($message)) . "</div>
                </div>
                
                <div class='field'>
                    <span class='label'>Submission Details</span>
                    <div class='value'>
                        Date: " . date('F j, Y') . "<br>
                        Time: " . date('g:i A') . "<br>
                        IP Address: " . $_SERVER['REMOTE_ADDR'] . "
                    </div>
                </div>
            </div>
            
            <div class='footer'>
                <p>This email was automatically generated from the Adams+ website contact form.</p>
                <p>&copy; " . date('Y') . " Adams+ Digital Solutions. All rights reserved.</p>
                <p style='font-size: 11px; color: #9ca3af;'>This is an automated message, please do not reply directly to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

function createConfirmationBody($firstName, $plan, $reference) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; }
            .header { background: #10b981; color: white; padding: 30px 20px; text-align: center; }
            .content { padding: 30px; background: #f9fafb; }
            .highlight { background: #d1fae5; padding: 15px; border-radius: 6px; margin: 20px 0; text-align: center; border: 2px dashed #10b981; }
            .footer { background: #f3f4f6; padding: 20px; text-align: center; font-size: 12px; color: #6b7280; }
            .cta { text-align: center; margin: 30px 0; }
            .cta a { background: #2563eb; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; display: inline-block; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1 style='margin: 0; font-size: 24px;'>Thank You, $firstName!</h1>
                <p style='margin: 10px 0 0; opacity: 0.9;'>Your Project Inquiry Has Been Received</p>
            </div>
            
            <div class='content'>
                <p>Dear $firstName,</p>
                
                <p>Thank you for choosing <strong>Adams+ Digital Solutions</strong>. We're excited to learn more about your project and help bring your vision to life.</p>
                
                <div class='highlight'>
                    <h3 style='margin-top: 0; color: #065f46;'>Project Details</h3>
                    <p style='margin-bottom: 5px;'><strong>Selected Package:</strong> $plan</p>
                    <p style='margin: 0;'><strong>Reference Number:</strong> $reference</p>
                </div>
                
                <h3>What Happens Next?</h3>
                <ol>
                    <li>Our team will review your inquiry within <strong>24 hours</strong></li>
                    <li>We'll contact you to discuss your requirements in detail</li>
                    <li>We'll prepare a customized proposal for your project</li>
                    <li>Once approved, we'll begin working on your project</li>
                </ol>
                
                <div class='cta'>
                    <p><strong>Have questions or need immediate assistance?</strong></p>
                    <a href='mailto:hello@adamsplusx.com'>Contact Our Team</a>
                </div>
                
                <p>You can also reach us directly:</p>
                <ul>
                    <li>Email: <a href='mailto:hello@adamsplusx.com'>hello@adamsplusx.com</a></li>
                    <li>Phone: +255 746 692 640 / +255 744 726 945</li>
                    <li>Location: Arusha, Tanzania</li>
                </ul>
                
                <p>Best regards,<br>
                <strong>The Adams+ Team</strong><br>
                <em>Premium Website Solutions</em></p>
            </div>
            
            <div class='footer'>
                <p>Adams+ Digital Solutions &copy; " . date('Y') . "</p>
                <p style='font-size: 11px; color: #9ca3af;'>
                    This is an automated confirmation email. Please do not reply to this message.<br>
                    If you didn't submit this inquiry, please ignore this email.
                </p>
            </div>
        </div>
    </body>
    </html>
    ";
}

function createPlainTextBody($firstName, $lastName, $email, $plan, $message, $reference) {
    return "
NEW PROJECT INQUIRY - Adams+ Digital Solutions
==============================================

Reference: $reference
Date: " . date('F j, Y') . "
Time: " . date('g:i A') . "

CLIENT INFORMATION:
------------------
Name: $firstName $lastName
Email: $email

SELECTED PACKAGE:
----------------
$plan

PROJECT DETAILS:
---------------
$message

SUBMISSION DETAILS:
------------------
IP Address: " . $_SERVER['REMOTE_ADDR'] . "

==============================================
This inquiry was submitted through the Adams+ website contact form.
Please respond within 24 hours.
==============================================
    ";
}

function createPlainConfirmationBody($firstName, $plan, $reference) {
    return "
THANK YOU FOR CONTACTING ADAMS+ DIGITAL SOLUTIONS
=================================================

Dear $firstName,

Thank you for your interest in Adams+ Digital Solutions. We have received 
your inquiry for our $plan package.

REFERENCE NUMBER: $reference

Our team will review your project details and contact you within 24 hours 
to discuss your requirements further.

WHAT TO EXPECT:
1. Initial contact within 24 hours
2. Detailed discussion about your project needs
3. Customized proposal and timeline
4. Project kickoff once approved

For immediate assistance:
Email: hello@adamsplusx.com
Phone: +255 746 692 640 / +255 744 726 945

We look forward to working with you!

Best regards,
The Adams+ Team
Adams+ Digital Solutions
Arusha, Tanzania

=================================================
This is an automated confirmation email.
If you didn't submit this inquiry, please ignore this message.
=================================================
    ";
}

function createSimpleEmailBody($firstName, $lastName, $email, $plan, $message, $reference) {
    return "
    <h2>New Project Inquiry</h2>
    <p><strong>Reference:</strong> $reference</p>
    <p><strong>Name:</strong> $firstName $lastName</p>
    <p><strong>Email:</strong> $email</p>
    <p><strong>Plan:</strong> $plan</p>
    <p><strong>Message:</strong></p>
    <p>" . nl2br(htmlspecialchars($message)) . "</p>
    <p><strong>Submitted:</strong> " . date('Y-m-d H:i:s') . "</p>
    ";
}
?>