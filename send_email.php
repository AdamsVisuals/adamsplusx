<?php
// send_email.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit();
}

// Check honeypot fields
if (isset($input['contact_me_by_fax_only']) && !empty($input['contact_me_by_fax_only'])) {
    // This is likely spam
    echo json_encode(['success' => true, 'message' => 'Submission received']); // Fake success for spam
    exit();
}

if (isset($input['website']) && !empty($input['website'])) {
    echo json_encode(['success' => true, 'message' => 'Submission received']);
    exit();
}

// Validate required fields based on form type
$formType = $input['form_type'] ?? 'unknown';
$errors = [];

switch ($formType) {
    case 'project_inquiry':
        if (empty($input['first_name'])) $errors[] = 'First name is required';
        if (empty($input['last_name'])) $errors[] = 'Last name is required';
        if (empty($input['email'])) $errors[] = 'Email is required';
        if (empty($input['plan'])) $errors[] = 'Plan is required';
        if (empty($input['message'])) $errors[] = 'Message is required';
        break;
        
    case 'quick_contact':
        if (empty($input['name'])) $errors[] = 'Name is required';
        if (empty($input['email'])) $errors[] = 'Email is required';
        if (empty($input['message'])) $errors[] = 'Message is required';
        break;
        
    case 'newsletter':
        if (empty($input['email'])) $errors[] = 'Email is required';
        break;
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit();
}

// Validate email
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
}

// Sanitize inputs
function sanitize($data) {
    if (!is_string($data)) {
        return $data;
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

foreach ($input as $key => $value) {
    if (is_string($value)) {
        $input[$key] = sanitize($value);
    }
}

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// Database configuration (optional - for storing submissions)
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'adamsplus_db',
    'username' => 'root',
    'password' => ''
];

try {
    // Store in database if available
    $storedInDB = false;
    try {
        $pdo = new PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
            $dbConfig['username'],
            $dbConfig['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        
        // Create table if not exists (updated to include selected_currency)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS form_submissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                form_type VARCHAR(50),
                first_name VARCHAR(100),
                last_name VARCHAR(100),
                email VARCHAR(255),
                phone VARCHAR(50),
                company VARCHAR(255),
                plan VARCHAR(100),
                budget VARCHAR(100),
                timeline VARCHAR(100),
                message TEXT,
                page_url VARCHAR(500),
                selected_currency VARCHAR(10),
                user_agent TEXT,
                ip_address VARCHAR(45),
                submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_form_type (form_type),
                INDEX idx_email (email),
                INDEX idx_submitted_at (submitted_at),
                INDEX idx_currency (selected_currency)
            )
        ");
        
        // Prepare insert statement (updated to include selected_currency)
        $stmt = $pdo->prepare("
            INSERT INTO form_submissions 
            (form_type, first_name, last_name, email, phone, company, plan, budget, timeline, message, page_url, selected_currency, user_agent, ip_address)
            VALUES 
            (:form_type, :first_name, :last_name, :email, :phone, :company, :plan, :budget, :timeline, :message, :page_url, :selected_currency, :user_agent, :ip_address)
        ");
        
        $data = [
            ':form_type' => $input['form_type'] ?? 'unknown',
            ':first_name' => $input['first_name'] ?? $input['name'] ?? null,
            ':last_name' => $input['last_name'] ?? null,
            ':email' => $input['email'] ?? null,
            ':phone' => $input['phone'] ?? null,
            ':company' => $input['company'] ?? null,
            ':plan' => $input['plan'] ?? null,
            ':budget' => $input['budget'] ?? null,
            ':timeline' => $input['timeline'] ?? null,
            ':message' => $input['message'] ?? null,
            ':page_url' => $input['page_url'] ?? null,
            ':selected_currency' => $input['selected_currency'] ?? null,
            ':user_agent' => $input['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ];
        
        $stmt->execute($data);
        $storedInDB = true;
        
    } catch (PDOException $e) {
        // Database connection failed, continue with email
        error_log("Database error: " . $e->getMessage());
    }
    
    // Send email using PHPMailer
    $mail = new PHPMailer(true);
    
    // SMTP Configuration (Update with your SMTP settings)
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com'; // Your SMTP server
    $mail->SMTPAuth = true;
    $mail->Username = 'hello@adamsplusx.com'; // Your email
    $mail->Password = 'Mihanda089564@'; // Your app password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    // Sender and recipient
    $mail->setFrom('hello@adamsplusx.com', 'Adams+ Website');
    $mail->addAddress('hello@adamsplusx.com', 'Adams+ Team'); // Your receiving email
    
    // Set reply-to email
    $replyToName = '';
    if ($formType === 'project_inquiry') {
        $replyToName = trim(($input['first_name'] ?? '') . ' ' . ($input['last_name'] ?? ''));
    } elseif ($formType === 'quick_contact') {
        $replyToName = $input['name'] ?? '';
    }
    
    if (!empty($input['email'])) {
        $mail->addReplyTo($input['email'], $replyToName);
    }
    
    // CC for specific forms
    if ($formType === 'project_inquiry') {
        $mail->addCC('sales@adamsplusx.com');
    }
    
    // Email content based on form type
    $subject = '';
    $body = '';
    
    switch ($formType) {
        case 'project_inquiry':
            $selectedCurrency = $input['selected_currency'] ?? 'USD';
            $currencySymbol = getCurrencySymbol($selectedCurrency);
            
            $subject = "New Project Inquiry: " . ($input['plan'] ?? 'Unknown Plan');
            $body = "
            <h2>New Project Inquiry</h2>
            <p><strong>Plan:</strong> " . ($input['plan'] ?? 'Not specified') . "</p>
            <p><strong>Name:</strong> " . ($input['first_name'] ?? '') . " " . ($input['last_name'] ?? '') . "</p>
            <p><strong>Email:</strong> " . ($input['email'] ?? '') . "</p>
            <p><strong>Phone:</strong> " . ($input['phone'] ?? 'Not provided') . "</p>
            <p><strong>Company:</strong> " . ($input['company'] ?? 'Not provided') . "</p>
            <p><strong>Budget:</strong> " . ($input['budget'] ?? 'Not specified') . "</p>
            <p><strong>Selected Currency:</strong> " . $selectedCurrency . " (" . $currencySymbol . ")</p>
            <p><strong>Timeline:</strong> " . ($input['timeline'] ?? 'Not specified') . "</p>
            <h3>Project Details:</h3>
            <p>" . ($input['message'] ?? '') . "</p>
            <hr>
            <p><small>Submitted from: " . ($input['page_url'] ?? 'Unknown') . "</small></p>
            <p><small>IP Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "</small></p>
            <p><small>Time: " . date('Y-m-d H:i:s') . "</small></p>
            ";
            break;
            
        case 'quick_contact':
            $subject = "Quick Contact Message from " . ($input['name'] ?? 'Visitor');
            $body = "
            <h2>Quick Contact Message</h2>
            <p><strong>Name:</strong> " . ($input['name'] ?? '') . "</p>
            <p><strong>Email:</strong> " . ($input['email'] ?? '') . "</p>
            <h3>Message:</h3>
            <p>" . ($input['message'] ?? '') . "</p>
            <hr>
            <p><small>Submitted from: " . ($input['page_url'] ?? 'Unknown') . "</small></p>
            <p><small>Time: " . date('Y-m-d H:i:s') . "</small></p>
            ";
            break;
            
        case 'newsletter':
            $subject = "New Newsletter Subscription";
            $body = "
            <h2>New Newsletter Subscriber</h2>
            <p><strong>Email:</strong> " . ($input['email'] ?? '') . "</p>
            <hr>
            <p><small>Subscribed from: " . ($input['page_url'] ?? 'Unknown') . "</small></p>
            <p><small>Time: " . date('Y-m-d H:i:s') . "</small></p>
            ";
            break;
    }
    
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->AltBody = strip_tags($body);
    
    // Send email
    $mail->send();
    
    // Send auto-reply to user (not for newsletter signups)
    if ($formType !== 'newsletter') {
        $replyMail = new PHPMailer(true);
        $replyMail->isSMTP();
        $replyMail->Host = 'smtp.hostinger.com';
        $replyMail->SMTPAuth = true;
        $replyMail->Username = 'hello@adamsplusx.com';
        $replyMail->Password = 'Mihanda089564@';
        $replyMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $replyMail->Port = 587;
        
        $replyMail->setFrom('hello@adamsplusx.com', 'Adams+ Team');
        $replyMail->addAddress($input['email']);
        
        // Get recipient name for greeting
        $recipientName = '';
        if ($formType === 'project_inquiry') {
            $recipientName = $input['first_name'] ?? 'Valued Client';
        } elseif ($formType === 'quick_contact') {
            $recipientName = $input['name'] ?? 'Valued Client';
        }
        
        if ($formType === 'project_inquiry') {
            $selectedCurrency = $input['selected_currency'] ?? 'USD';
            $currencySymbol = getCurrencySymbol($selectedCurrency);
            
            $replySubject = "Thank you for your project inquiry!";
            $replyBody = "
            <h2>Thank You for Contacting Adams+</h2>
            <p>Dear {$recipientName},</p>
            <p>Thank you for your interest in our <strong>" . ($input['plan'] ?? 'selected') . "</strong> package. 
            We've received your project details and our team will review them shortly.</p>
            
            <h3>What's Next?</h3>
            <ul>
                <li>Our team will contact you within 24 hours to discuss your project in detail</li>
                <li>We'll provide a customized proposal based on your requirements</li>
                <li>We'll schedule a discovery call to understand your vision better</li>
            </ul>
            
            <p><strong>Your Inquiry Details:</strong></p>
            <ul>
                <li>Package: " . ($input['plan'] ?? 'Not specified') . "</li>
                <li>Budget Range: " . ($input['budget'] ?? 'Not specified') . "</li>
                <li>Selected Currency: " . $selectedCurrency . "</li>
                <li>Timeline: " . ($input['timeline'] ?? 'Not specified') . "</li>
                <li>Submitted: " . date('F j, Y, g:i a') . "</li>
            </ul>
            
            <p>In the meantime, feel free to explore our <a href='https://adamsplusx.com/#portfolio'>portfolio</a> 
            to see examples of our work.</p>
            
            <p>Best regards,<br>
            <strong>The Adams+ Team</strong></p>
            
            <hr>
            <p style='font-size: 12px; color: #666;'>
                Adams+ Digital Solutions<br>
                Arusha, Tanzania<br>
                Phone: +255 746 692 640 | +255 744 726 945<br>
                Email: hello@adamsplusx.com<br>
                Website: https://adamsplusx.com
            </p>
            ";
        } else {
            $replySubject = "Thank you for contacting Adams+";
            $replyBody = "
            <h2>Thank You for Contacting Adams+</h2>
            <p>Dear {$recipientName},</p>
            <p>Thank you for reaching out to Adams+ Digital Solutions. 
            We've received your message and will get back to you within 24 hours.</p>
            
            <p>In the meantime, you can:</p>
            <ul>
                <li>Browse our <a href='https://adamsplusx.com/#portfolio'>portfolio</a></li>
                <li>Check our <a href='https://adamsplusx.com/#pricing'>pricing</a> packages</li>
                <li>Learn about our <a href='https://adamsplusx.com/#services'>services</a></li>
            </ul>
            
            <p>We look forward to helping you bring your digital vision to life!</p>
            
            <p>Best regards,<br>
            <strong>The Adams+ Team</strong></p>
            
            <hr>
            <p style='font-size: 12px; color: #666;'>
                Adams+ Digital Solutions<br>
                Arusha, Tanzania<br>
                Phone: +255 746 692 640 | +255 744 726 945<br>
                Email: hello@adamsplusx.com<br>
                Website: https://adamsplusx.com
            </p>
            ";
        }
        
        $replyMail->isHTML(true);
        $replyMail->Subject = $replySubject;
        $replyMail->Body = $replyBody;
        $replyMail->AltBody = strip_tags($replyBody);
        
        try {
            $replyMail->send();
        } catch (Exception $e) {
            // Log but don't fail the main submission
            error_log("Auto-reply failed: " . $e->getMessage());
        }
    }
    
    // Log submission
    $logMessage = date('Y-m-d H:i:s') . " - {$formType} submission from " . ($input['email'] ?? 'unknown') . " (Currency: " . ($input['selected_currency'] ?? 'N/A') . ")";
    error_log($logMessage);
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your submission has been received.',
        'data' => [
            'form_type' => $formType,
            'stored_in_db' => $storedInDB,
            'selected_currency' => $input['selected_currency'] ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    // Log error
    error_log("Email sending failed: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send email. Please try again later.',
        'error' => $e->getMessage()
    ]);
}

// Helper function to get currency symbol
function getCurrencySymbol($currencyCode) {
    $currencySymbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'CAD' => 'CA$',
        'AUD' => 'AU$',
        'CHF' => 'CHF ',
        'CNY' => 'CN¥',
        'INR' => '₹',
        'TZS' => 'TZS ',
        'KES' => 'KES ',
        'NGN' => '₦',
        'ZAR' => 'R',
        'AED' => 'AED ',
        'SAR' => 'SAR ',
        'BRL' => 'R$',
        'MXN' => 'MX$'
    ];
    
    return $currencySymbols[$currencyCode] ?? $currencyCode;
}
?>