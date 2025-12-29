<?php
// submit_review.php - FINAL PROFESSIONAL VERSION

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

// 1. SETUP & ERROR HANDLING
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start(); // Buffer output to prevent stray text from breaking JSON

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST');

$response = ['success' => false, 'status' => 'error', 'message' => 'Unknown Error'];

try {
    // 2. LOAD PHPMAILER
    // We check for the file first to be safe
    $phpMailerPath = __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
    if (!file_exists($phpMailerPath)) {
        throw new Exception("PHPMailer not found. Check folder structure.");
    }

    require __DIR__ . '/PHPMailer-master/src/Exception.php';
    require __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
    require __DIR__ . '/PHPMailer-master/src/SMTP.php';

    // 3. VALIDATE INPUT
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid Method. POST required.");
    }
    
    $input = $_POST;
    // Honeypot check (Spam prevention)
    if (!empty($input['website'])) {
        echo json_encode(['success' => true, 'message' => 'Sent']); 
        exit;
    }

    // 4. SANITIZE DATA
    $name = htmlspecialchars(trim($input['name'] ?? 'Anonymous'));
    $email = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $rating = intval($input['rating'] ?? 0);
    $message = htmlspecialchars(trim($input['message'] ?? ''));
    $permission = htmlspecialchars($input['permission'] ?? 'yes');
    $submission_time = date('Y-m-d H:i:s');
    $review_id = strtoupper(uniqid('REV_'));

    // 5. SAVE TO JSON FILE (Backup)
    $dirPath = __DIR__ . '/reviews';
    if (!is_dir($dirPath)) {
        if (!mkdir($dirPath, 0755, true)) {
            // If we can't create folder, log it but don't crash user experience
            error_log("Cannot create reviews folder"); 
        }
    }
    
    $filename = $dirPath . '/reviews_' . date('Y-m') . '.json';
    $reviews = [];
    if (file_exists($filename)) {
        $content = file_get_contents($filename);
        $reviews = json_decode($content, true) ?? [];
    }

    $newReview = [
        'id' => $review_id,
        'timestamp' => $submission_time,
        'name' => $name,
        'email' => $email,
        'rating' => $rating,
        'message' => $message,
        'permission' => $permission
    ];
    
    // Add to top of list
    array_unshift($reviews, $newReview);
    
    // Save to file (Silent failure if write fails, so email still sends)
    @file_put_contents($filename, json_encode($reviews, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));


    // 6. PREPARE EMAIL VARIABLES (Design Logic)
    // We calculate these HERE so the HTML block is clean and error-free
    $starSymbol = '★'; 
    $emptyStar = '☆';
    $starsDisplay = str_repeat($starSymbol, $rating) . str_repeat($emptyStar, 5 - $rating);
    
    // Badge Styles
    if ($permission === 'yes') {
        $badgeBg = '#dcfce7'; // Green background
        $badgeText = '#166534'; // Green text
        $permLabel = 'Public Display Allowed';
        $permDesc = 'Client agreed to showcase this review on the website.';
    } else {
        $badgeBg = '#fee2e2'; // Red background
        $badgeText = '#991b1b'; // Red text
        $permLabel = 'Private Feedback';
        $permDesc = 'Internal use only. Do not publish.';
    }

    // 7. HTML EMAIL TEMPLATE (Swiss Style)
    // Using a simpler string concatenation to avoid HEREDOC issues on some servers
    $emailBody = '
    <!DOCTYPE html>
    <html>
    <body style="font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; background-color: #f5f5f4; margin: 0; padding: 20px;">
        
        <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
            
            <div style="background-color: #0c0a09; padding: 32px; text-align: center;">
                <div style="color: #3b82f6; font-size: 12px; text-transform: uppercase; letter-spacing: 2px; font-weight: bold; margin-bottom: 8px;">/ New Submission</div>
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; letter-spacing: -0.5px;">Client Review</h1>
            </div>

            <div style="background-color: #fafaf9; padding: 24px; text-align: center; border-bottom: 1px solid #e7e5e4;">
                <div style="font-size: 32px; color: #fbbf24; margin-bottom: 4px; letter-spacing: 4px;">' . $starsDisplay . '</div>
                <div style="color: #78716c; font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">' . $rating . ' / 5.0 Rating</div>
            </div>

            <div style="padding: 32px;">
                
                <div style="margin-bottom: 24px;">
                    <label style="display: block; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #a8a29e; margin-bottom: 8px;">Client Message</label>
                    <div style="font-size: 16px; line-height: 1.6; color: #1c1917; background: #f5f5f4; padding: 16px; border-left: 3px solid #3b82f6; border-radius: 4px;">
                        "' . $message . '"
                    </div>
                </div>

                <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
                    <tr>
                        <td style="padding: 12px 0; border-bottom: 1px solid #f5f5f4; color: #78716c; font-size: 14px; width: 100px;">Name</td>
                        <td style="padding: 12px 0; border-bottom: 1px solid #f5f5f4; color: #1c1917; font-weight: bold; font-size: 14px;">' . $name . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 12px 0; border-bottom: 1px solid #f5f5f4; color: #78716c; font-size: 14px;">Email</td>
                        <td style="padding: 12px 0; border-bottom: 1px solid #f5f5f4; color: #3b82f6; font-size: 14px;">' . $email . '</td>
                    </tr>
                </table>

                <div style="background-color: ' . $badgeBg . '; border-radius: 8px; padding: 16px; display: flex; align-items: start;">
                    <div>
                        <span style="color: ' . $badgeText . '; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 4px;">' . $permLabel . '</span>
                        <span style="color: ' . $badgeText . '; font-size: 13px; opacity: 0.8;">' . $permDesc . '</span>
                    </div>
                </div>

            </div>

            <div style="background-color: #f5f5f4; padding: 16px; text-align: center; font-size: 11px; color: #a8a29e;">
                ID: ' . $review_id . ' • ' . $submission_time . '
            </div>

        </div>
    </body>
    </html>';


    // 8. SEND EMAIL VIA PHPMAILER
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'hello@adamsplusx.com'; 
    $mail->Password   = 'Mihanda089564@'; 
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->Timeout    = 15;

    $mail->setFrom('hello@adamsplusx.com', 'Adams+ Website');
    $mail->addAddress('sales@adamsplusx.com'); // Admin email
    $mail->addReplyTo($email, $name); // Reply to client directly
    
    $mail->isHTML(true);
    $mail->Subject = "★ New Review: {$rating} Stars from {$name}";
    $mail->Body    = $emailBody;
    $mail->AltBody = "New Review from $name ($rating/5): $message";

    $mail->send();

    // 9. RETURN SUCCESS
    $response['success'] = true;
    $response['status'] = 'success';
    $response['message'] = 'Review sent successfully!';

} catch (Exception $e) {
    // CATCH CUSTOM ERRORS
    $response['message'] = 'Error: ' . $e->getMessage();
} catch (\Throwable $t) {
    // CATCH FATAL PHP ERRORS
    $response['message'] = 'Server Error: ' . $t->getMessage();
}

// Clean buffer and output JSON
ob_end_clean(); 
echo json_encode($response);
?>