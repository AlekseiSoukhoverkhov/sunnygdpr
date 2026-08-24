<?php
/**
 * Thank You & Processing Gateway Template
 * Isolated static file (runs without WordPress core overhead).
 * All code comments are strictly in English.
 */

// Helper function to sanitize URLs without loading WordPress core
function sunnycom_clean_url( $url ) {
    $url = filter_var( $url, FILTER_SANITIZE_URL );
    return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
}

// Retrieve sanitized target URL and delay parameter using native PHP
$raw_to      = isset( $_GET['to'] ) ? urldecode( $_GET['to'] ) : '/';
$redirect_to = sunnycom_clean_url( $raw_to );
$delay       = isset( $_GET['delay'] ) ? (int) $_GET['delay'] : 8;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Thank You For Your Comment</title>
    <!-- Automatic client-side redirect after specified delay -->
    <meta http-equiv="refresh" content="<?php echo $delay; ?>;url=<?php echo $redirect_to; ?>">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f6f8;
            color: #2c3e50;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .thankyou-card {
            background: #ffffff;
            padding: 40px 30px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            text-align: center;
            max-width: 480px;
            width: 100%;
        }
        .loader-icon {
            width: 50px;
            height: 50px;
            border: 4px solid #e2e8f0;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 24px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        h1 {
            font-size: 22px;
            margin-bottom: 12px;
            color: #1e293b;
        }
        p {
            font-size: 15px;
            line-height: 1.6;
            color: #64748b;
            margin-bottom: 20px;
        }
        .redirect-note {
            font-size: 13px;
            color: #94a3b8;
        }
        .redirect-note a {
            color: #3498db;
            text-decoration: none;
        }
        .redirect-note a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="thankyou-card">
        <div class="loader-icon"></div>
        <h1>Thank you for your comment!</h1>
        <p>Your message has been received and is currently being processed by our moderation system. Please wait a few seconds...</p>
        <p class="redirect-note">
            You will be automatically redirected to an interesting article on our blog.<br>
            If you are not redirected automatically, <a href="<?php echo $redirect_to; ?>">click here</a>.
        </p>
    </div>
</body>
</html>