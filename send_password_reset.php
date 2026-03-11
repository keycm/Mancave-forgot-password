<?php
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(403);
    exit("Forbidden");
}

$email = trim($_POST["email"] ?? '');

if (empty($email)) {
    $_SESSION['error_message'] = "Email is required.";
    $_SESSION['show_forgot_modal'] = true;
    header("Location: index.php");
    exit();
}

// 1. Verify if the email actually exists first
$check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check_stmt->bind_param("s", $email);
$check_stmt->execute();

if ($check_stmt->get_result()->num_rows > 0) {
    
    // Generate a 6-digit secure random OTP
    $otp = random_int(100000, 999999);
    $otp_hash = hash("sha256", $otp); 
    $expiry = date("Y-m-d H:i:s", time() + 60 * 15); // Expires in 15 minutes

    // Update user in the database
    $sql = "UPDATE users SET reset_token_hash = ?, reset_token_expires_at = ? WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $otp_hash, $expiry, $email);
    $stmt->execute();

    // Bring in the PHPMailer setup
    $mail = require __DIR__ . "/reset_mailer.php";
    
    $mail->setFrom("gallerymancave@gmail.com", "ManCave Gallery Support");
    $mail->addAddress($email);
    $mail->Subject = "Your Password Reset OTP - ManCave Gallery";

    $mail->isHTML(true);
    $mail->Body = <<<HTML
        <div style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
            <h2 style="color: #374151;">Password Reset Request</h2>
            <p>Hello,</p>
            <p>We received a request to reset your password for your <strong>ManCave Gallery</strong> account.</p>
            <p>Here is your 6-digit One-Time Password (OTP):</p>
            <div style="margin: 25px 0; text-align: center;">
                <span style="background-color: #f36c21; color: white; padding: 15px 30px; border-radius: 5px; font-weight: bold; font-size: 28px; letter-spacing: 5px;">{$otp}</span>
            </div>
            <p>If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>
            <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
            <p style="font-size: 12px; color: #777;">This OTP will expire in 15 minutes.</p>
        </div>
    HTML;

    try {
        $mail->send();
        $_SESSION['success_message'] = "OTP sent! Please check your email inbox.";
        $_SESSION['reset_email'] = $email; 
        $_SESSION['show_reset_otp_modal'] = true; // Use session instead of URL param
        header("Location: index.php");
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Message could not be sent. Mailer error: {$mail->ErrorInfo}";
        $_SESSION['show_forgot_modal'] = true;
        header("Location: index.php");
        exit();
    }
} else {
    $_SESSION['error_message'] = "No account found with that email address.";
    $_SESSION['show_forgot_modal'] = true;
    header("Location: index.php"); 
    exit();
}
?>
