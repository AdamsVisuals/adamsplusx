<?php
// send_review.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = ['success' => false, 'message' => 'An error occurred'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $response['message'] = 'Invalid JSON input';
    echo json_encode($response);
    exit;
}

// Check honeypot field
if (!empty($input['website'])) {
    $response['message'] = 'Spam detected';
    echo json_encode($response);
    exit;
}

// Validate required fields
$requiredFields = ['name', 'email', 'rating', 'title', 'message'];
foreach ($requiredFields as $field) {
    if (empty($input[$field])) {
        $response['message'] = 'Missing required fields';
        echo json_encode($response);
        exit;
    }
}

// Sanitize inputs
$name = htmlspecialchars(trim($input['name']));
$email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
$company = !empty($input['company']) ? htmlspecialchars(trim($input['company'])) : 'Not provided';
$rating = intval($input['rating']);
$title = htmlspecialchars(trim($input['title']));
$message = htmlspecialchars(trim($input['message']));
$category = !empty($input['category']) ? htmlspecialchars(trim($input['category'])) : 'General';
$permission = !empty($input['permission']) ? htmlspecialchars(trim($input['permission'])) : 'yes';
$page_url = !empty($input['page_url']) ? htmlspecialchars(trim($input['page_url'])) : 'Unknown';
$submission_time = !empty($input['submission_time']) ? htmlspecialchars(trim($input['submission_time'])) : date('Y-m-d H:i:s');

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Invalid email address';
    echo json_encode($response);
    exit;
}

// Validate rating
if ($rating < 1 || $rating > 5) {
    $response['message'] = 'Invalid rating';
    echo json_encode($response);
    exit;
}

try {
    $mail = new PHPMailer(true);
    
    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com'; // Replace with your SMTP host
    $mail->SMTPAuth = true;
    $mail->Username = 'hello@adamsplusx.com'; // Replace with your email
    $mail->Password = 'Mihanda089564@'; // Replace with your email password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    // Recipients
    $mail->setFrom('hello@adamsplusx.com', 'Adams+ Website');
    $mail->addAddress('sales@adamsplusx.com', 'Adams+ Sales'); // Your sales email
    
    // Optional CC to yourself
    $mail->addCC('hello@adamsplusx.com', 'Adams+ Team');
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = "New Review Submission: {$title}";
    
    // Email body
    $emailBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #3b82f6; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
            .content { background: #f9fafb; padding: 20px; border: 1px solid #e5e7eb; }
            .footer { background: #f3f4f6; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; }
            .rating { color: #fbbf24; font-size: 18px; }
            .field { margin-bottom: 15px; }
            .field-label { font-weight: bold; color: #4b5563; }
            .field-value { color: #111827; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🌟 New Review Submitted</h2>
                <p>Rating: <span class='rating'>" . str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) . " ({$rating}/5)</span></p>
            </div>
            
            <div class='content'>
                <div class='field'>
                    <div class='field-label'>Review Title:</div>
                    <div class='field-value'><strong>{$title}</strong></div>
                </div>
                
                <div class='field'>
                    <div class='field-label'>Reviewer:</div>
                    <div class='field-value'>{$name} ({$email})</div>
                </div>
                
                <div class='field'>
                    <div class='field-label'>Company/Role:</div>
                    <div class='field-value'>{$company}</div>
                </div>
                
                <div class='field'>
                    <div class='field-label'>Category:</div>
                    <div class='field-value'>{$category}</div>
                </div>
                
                <div class='field'>
                    <div class='field-label'>Permission to Display:</div>
                    <div class='field-value'>" . ($permission === 'yes' ? '✅ Yes, display publicly' : '❌ No, keep private') . "</div>
                </div>
                
                <div class='field'>
                    <div class='field-label'>Review Message:</div>
                    <div class='field-value'><p>{$message}</p></div>
                </div>
                
                <div class='field'>
                    <div class='field-label'>Submission Details:</div>
                    <div class='field-value'>
                        <p>Submitted from: {$page_url}</p>
                        <p>Time: " . date('F j, Y g:i A', strtotime($submission_time)) . "</p>
                    </div>
                </div>
            </div>
            
            <div class='footer'>
                <p>This review was submitted via the Adams+ website contact form.</p>
                <p>Review ID: " . uniqid('REV_') . "</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $mail->Body = $emailBody;
    
    // Plain text version
    $mail->AltBody = "New Review Submission\n\n" .
                     "Reviewer: {$name} ({$email})\n" .
                     "Rating: {$rating}/5\n" .
                     "Title: {$title}\n" .
                     "Company: {$company}\n" .
                     "Category: {$category}\n" .
                     "Permission: " . ($permission === 'yes' ? 'Public' : 'Private') . "\n" .
                     "Message: {$message}\n\n" .
                     "Submitted from: {$page_url}\n" .
                     "Time: " . date('F j, Y g:i A', strtotime($submission_time));
    
    // Send email
    if ($mail->send()) {
        // Optionally save to database or file
        saveReviewToFile($input);
        
        $response['success'] = true;
        $response['message'] = 'Thank you for your review! We will review it before publishing.';
        $response['data'] = [
            'review_id' => uniqid('REV_'),
            'timestamp' => $submission_time
        ];
    } else {
        $response['message'] = 'Failed to send email. Please try again.';
    }
    
} catch (Exception $e) {
    $response['message'] = "Mailer Error: {$mail->ErrorInfo}";
}

echo json_encode($response);

// Function to save review to a JSON file (optional)
function saveReviewToFile($data) {
    $filename = 'reviews/reviews_' . date('Y-m') . '.json';
    
    // Create directory if it doesn't exist
    if (!file_exists('reviews')) {
        mkdir('reviews', 0755, true);
    }
    
    $reviews = [];
    if (file_exists($filename)) {
        $reviews = json_decode(file_get_contents($filename), true);
    }
    
    // Add new review
    $reviews[] = [
        'id' => uniqid('REV_'),
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $data
    ];
    
    // Save back to file
    file_put_contents($filename, json_encode($reviews, JSON_PRETTY_PRINT));
}
?>