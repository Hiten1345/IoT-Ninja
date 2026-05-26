<?php
// --- General & Security Configuration ---
define('ADMIN_ID', 'admin');
define('ADMIN_PASS', 'Hiten1234'); // << CHANGE THIS PASSWORD!

// --- Email (PHPMailer) Configuration ---
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'hiten1345@gmail.com');      // Your Gmail address
define('SMTP_PASSWORD', 'rsgw zjej ohpr jjwn');         // Your 16-character Gmail App Password
define('SMTP_PORT', 587);                             // 587 for TLS, 465 for SSL
define('SMTP_SECURE', 'tls');                         // 'tls' or 'ssl'
define('SMTP_FROM_EMAIL', 'hiten1345@gmail.com');     // The "From" email address
define('SMTP_FROM_NAME', 'Ninja IoT');             // The "From" name

// Automatically detect the correct base URL (local or remote)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') 
            ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$current_dir = str_replace('\\', '/', __DIR__);
$sub_dir = str_ireplace($doc_root, '', $current_dir);
$sub_dir = trim($sub_dir, '/');
$dir = ($sub_dir !== '') ? '/' . $sub_dir . '/' : '/';
define('APP_BASE_URL', $protocol . $host . $dir);
define('GOOGLE_CLIENT_ID', '850040043737-90hmesgtbpkjg9ak41o3vnifju464per.apps.googleusercontent.com'); // Get from Google Cloud Console