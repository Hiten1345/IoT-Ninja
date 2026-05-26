<?php
// For development (uncomment if needed):
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
// Extend session lifetime to 30 days for "Stay Logged In" functionality
$session_lifetime = 30 * 24 * 60 * 60; // 30 days
ini_set('session.gc_maxlifetime', $session_lifetime);
session_set_cookie_params([
    'lifetime' => $session_lifetime,
    'path' => '/',
    'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'), // Dynamic SSL detection
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
date_default_timezone_set('Asia/Kolkata');

// --- Configuration & Initialization ---
require_once 'config.php';
define('DATA_DIR', 'data/');

// Ensure data directory exists
if (!is_dir(DATA_DIR)) {
    if (!mkdir(DATA_DIR, 0777, true) && !is_dir(DATA_DIR)) {
        error_log('FATAL: Failed to create data directory: ' . DATA_DIR);
        die('FATAL ERROR: Failed to create data directory. Please check server permissions.');
    }
}
// Initialize admin-configurable settings if not present
if (!file_exists(DATA_DIR . 'config.json')) {
    file_put_contents(DATA_DIR . 'config.json', json_encode([
        'allow_self_registration' => true,
        'admin_secret_path' => 'admin1205'
    ], JSON_PRETTY_PRINT));
}

require_once 'functions.php';


// --- API ROUTING (Handles AJAX requests) ---
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$uid_param = isset($_GET['UID']) ? trim($_GET['UID']) : null;
$api_key_param = isset($_GET['api_key']) ? trim($_GET['api_key']) : null;
$current_page = isset($_GET['page']) ? trim($_GET['page']) : 'login';

// --- Handle REST-like API URLs (api/v1/APIKEY/ACTION) ---
if ($current_page === 'api') {
    $uri_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri_path = str_replace('\\', '/', $uri_path); // Normalize
    $marker = 'api/v1/';
    $pos = strpos($uri_path, $marker);
    
    if ($pos !== false) {
        $sub_path = substr($uri_path, $pos + strlen($marker));
        $parts = explode('/', trim($sub_path, '/'));
        
        if (count($parts) >= 2) {
            $api_key_param = $parts[0];
            $action = $parts[1];
        }
    }
}

// Resolve ProjectID from API Key if provided
if ($api_key_param) {
    $project = getProjectByAPIKey($api_key_param);
    if ($project) {
        $uid_param = $project['ProjectID']; // Treat ProjectID as UID for data functions
    } else {
        if (!empty($action)) {
            http_response_code(403);
            echo "error:invalid_api_key";
            exit;
        }
    }
}

if (!empty($action)) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); header('Pragma: no-cache'); header('Expires: 0'); header('Content-Type: text/plain');
    if ($action === 'verify_api_key' && $api_key_param !== null) {
        header('Content-Type: application/json');
        $project = getProjectByAPIKey($api_key_param);
        if ($project) {
            echo json_encode(['valid' => true, 'project_id' => $project['ProjectID']]);
        } else {
            echo json_encode(['valid' => false]);
        }
        exit;
    }
    $allKnownSystemFields = DATA_FIELDS_ALLOWED_ARRAY;
    if ($action === 'write' && $uid_param !== null && $_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!isValidUID($uid_param)) { http_response_code(403); echo "error:invalid_or_unknown_uid"; exit; }
        $fieldWritten = false; 
        $dataToWrite = $_GET; 
        unset($dataToWrite['action'], $dataToWrite['UID'], $dataToWrite['api_key'], $dataToWrite['page']);
        
        // Remove keys that are likely rewrite artifacts (containing slashes)
        foreach (array_keys($dataToWrite) as $k) {
            if (strpos($k, '/') !== false) unset($dataToWrite[$k]);
        }

        if (empty($dataToWrite)) { http_response_code(400); echo "error:no_field_or_value_provided"; exit; }
        $field_to_write = null; $value_to_write = null;
        foreach($dataToWrite as $key => $value) { $field_to_write = trim($key); $value_to_write = trim($value); break; }
        if ($field_to_write !== null) { if (writeUserDataCSV($uid_param, $field_to_write, $value_to_write)) { echo "success"; } else { http_response_code(500); echo "error:failed_to_write_data"; }}
        else { http_response_code(400); echo "error:no_valid_field_or_value_provided_for_write"; }
        exit;
    }
    if (($action === 'read' || $action === 'read_all') && $uid_param !== null) {
        if (!isValidUID($uid_param)) {
            http_response_code(403); $requested_fields_str_for_error = isset($_GET['fields']) ? trim($_GET['fields']) : null;
            if ($action === 'read_all' || $requested_fields_str_for_error) { header('Content-Type: application/json'); echo json_encode(["error" => "invalid_or_unknown_uid"]); }
            else { echo "error:invalid_or_unknown_uid"; } exit;
        }
        $requested_fields_str = isset($_GET['fields']) ? trim($_GET['fields']) : null;
        if ($action === 'read_all') { 
            header('Content-Type: application/json'); 
            $projects = getProjectsByUser($uid_param);
            $response_data = [];
            if (!empty($projects)) {
                foreach ($projects as $proj) {
                    $pData = readUserDataCSV($proj['ProjectID'], null, false);
                    if (!empty($pData)) {
                        $pData['ProjectName'] = $proj['Name'];
                        $pData['ProjectID'] = $proj['ProjectID'];
                        $response_data[] = $pData;
                    }
                }
            } else {
                $uData = readUserDataCSV($uid_param, null, false);
                if (!empty($uData)) {
                    $uData['ProjectName'] = 'Direct User Data';
                    $response_data[] = $uData;
                }
            }
            echo json_encode($response_data); 
        }
        elseif ($requested_fields_str) {
            header('Content-Type: application/json'); 
            $requested_fields_arr = array_map('trim', explode(',', $requested_fields_str));
            $latest_data_for_uid = readUserDataCSV($uid_param, null, false); 
            $data_to_return = []; 
            $system_read_fields = ['Timestamp', 'UID'];
            
            $data_map_lower = [];
            foreach ($latest_data_for_uid as $k => $v) {
                $data_map_lower[strtolower($k)] = $v;
            }

            foreach ($requested_fields_arr as $field_key) {
                $field_key_lower = strtolower($field_key);
                if (array_key_exists($field_key_lower, $data_map_lower)) {
                    $data_to_return[$field_key] = $data_map_lower[$field_key_lower];
                } elseif (in_array($field_key, $system_read_fields)) {
                    $data_to_return[$field_key] = $latest_data_for_uid[$field_key] ?? ($field_key === 'UID' ? $uid_param : '');
                } else {
                    $data_to_return[$field_key] = ''; 
                }
            }
            echo json_encode($data_to_return);
        } else {
            $field_to_read = null; 
            $get_params_copy = $_GET; 
            unset($get_params_copy['action'], $get_params_copy['UID'], $get_params_copy['api_key'], $get_params_copy['page']);
            
            // Remove keys that are likely rewrite artifacts
            foreach (array_keys($get_params_copy) as $k) {
                if (strpos($k, '/') !== false) unset($get_params_copy[$k]);
            }

            if (!empty($get_params_copy)) { $field_to_read = key($get_params_copy); }
            
            if ($field_to_read !== null) { 
                $data = readUserDataCSV($uid_param, $field_to_read, false); 
                header('Content-Type: text/plain'); 
                echo isset($data[$field_to_read]) ? $data[$field_to_read] : ""; 
            }
            else { 
                header('Content-Type: application/json'); 
                $data = readUserDataCSV($uid_param, null, false); 
                echo json_encode(!empty($data) ? $data : new stdClass()); 
            }
        } exit;
    }
    if ($action === 'read_all' && $uid_param === null) { header('Content-Type: application/json'); $data = readAllUsersDataCSV(); echo json_encode($data); exit; }
    if ($action === 'read_history' && $uid_param !== null) {
        if (!isValidUID($uid_param)) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(["error" => "invalid_or_unknown_uid"]); exit; }
        header('Content-Type: application/json'); $field_for_history = isset($_GET['field']) ? trim($_GET['field']) : null;
        if ($field_for_history) { $data = readUserDataCSV($uid_param, $field_for_history, true); echo json_encode($data); }
        else { echo json_encode([]); } exit;
    }
    if ($action === 'history' && $uid_param !== null) {
        if (!isValidUID($uid_param)) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(["error" => "invalid_or_unknown_uid"]); exit; }
        header('Content-Type: application/json'); 
        $requested_fields_str = isset($_GET['fields']) ? trim($_GET['fields']) : null;
        if ($requested_fields_str) {
            $fields_arr = array_map('trim', explode(',', $requested_fields_str));
            $data = readUserHistoryMultipleFields($uid_param, $fields_arr);
            echo json_encode($data);
        } else {
             echo json_encode([]); 
        } 
        exit;
    }
    if ($action === 'download_history' && $uid_param !== null) {
        if (!isValidUID($uid_param)) { http_response_code(403); header('Content-Type: text/plain'); echo "Error: Invalid or unknown User ID. Cannot download history."; exit; }
        stream_csv_directly('history_', $uid_param, function($outputHandle) use ($uid_param) {
            fputcsv($outputHandle, ['Timestamp', 'Field', 'Value']);
            $db = getDB();
            $stmt = $db->prepare("SELECT recorded_at, field, value FROM iot_history WHERE project_id = ? ORDER BY recorded_at DESC");
            $stmt->execute([$uid_param]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($outputHandle, [$row['recorded_at'], $row['field'], $row['value']]);
            }
        });
    }
    if ($action === 'download_user_list') {
        stream_csv_directly('user_list_', 'all', function($outputHandle) {
            fputcsv($outputHandle, ['UID', 'FirstName', 'LastName', 'BornYear', 'Email', 'Status']);
            $allUsers = getAllUsers();
            foreach ($allUsers as $user) {
                fputcsv($outputHandle, [
                    $user['UID'] ?? '',
                    $user['FirstName'] ?? '',
                    $user['LastName'] ?? '',
                    $user['BornYear'] ?? '',
                    $user['Email'] ?? '',
                    $user['Status'] ?? ''
                ]);
            }
        });
    }
    if ($action === 'download_user_template') {
        stream_csv_directly('bulk_user_template_', 'template', function($outputHandle) {
            fputcsv($outputHandle, ['FirstName', 'LastName', 'Email', 'BornYear', 'Password']);
        });
    }
    if ($action === 'google_login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_token = $_POST['credential'] ?? '';
        $google_user = verifyGoogleToken($id_token);
        
        if ($google_user) {
            $email = trim(strtolower($google_user['email']));
            $user = getUserBy('Email', $email);
            
            if ($user) {
                // User exists, log them in
                $_SESSION['user_uid'] = $user['UID'];
                
                // For Google login, we don't force password change
                if ($user['Status'] === 'pending_reset') {
                    header('Location: force-reset');
                } else {
                    header('Location: projects');
                }
                exit;
            } else {
                // User does not exist, redirect to registration with pre-filled data
                $_SESSION['google_signup_data'] = [
                    'email' => $email,
                    'first_name' => $google_user['given_name'] ?? '',
                    'last_name' => $google_user['family_name'] ?? ''
                ];
                header('Location: register');
                exit;
            }
        } else {
            $_SESSION['google_login_error'] = "Google authentication failed. Please try again.";
            header('Location: login');
            exit;
        }
    }
    http_response_code(400); echo "error:unknown_action_or_invalid_params"; exit;
}



// --- PAGE ROUTING ---
switch ($current_page) {
    case 'login':
        $error_message = '';
        if (isset($_SESSION['google_login_error'])) {
            $error_message = $_SESSION['google_login_error'];
            unset($_SESSION['google_login_error']);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {
            $email = trim(strtolower($_POST['email']));
            $password = $_POST['password'];
            if (!empty($email) && !empty($password)) {
                $user = getUserBy('Email', $email);
                if ($user && !empty($user['Password']) && password_verify($password, $user['Password'])) {
                    $_SESSION['user_uid'] = $user['UID'];
                    
                    // Check if user needs to change password
                    if (isset($user['ForcePasswordChange']) && $user['ForcePasswordChange'] == 1) {
                        header('Location: change-password');
                        exit;
                    }
                    
                    if ($user['Status'] === 'pending_reset') {
                        header('Location: force-reset');
                    } else {
                        header('Location: projects');
                    }
                    exit;
                } else { $error_message = "Invalid email or password."; }
            } else { $error_message = "Email and password cannot be empty."; }
        }
        include 'view-login.php';
        break;

    case 'change-password':
        if (!isset($_SESSION['user_uid'])) { header('Location: login'); exit; }
        $user = getUserBy('UID', $_SESSION['user_uid']);
        if (!$user) { session_destroy(); header('Location: login'); exit; }
        
        $error_message = ''; $success_message = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'], $_POST['confirm_password'])) {
            $new_pass = $_POST['new_password'];
            $confirm_pass = $_POST['confirm_password'];
            
            if (strlen($new_pass) < 8) {
                $error_message = "Password must be at least 8 characters long.";
            } elseif ($new_pass !== $confirm_pass) {
                $error_message = "Passwords do not match.";
            } else {
                if (updateUserRecord($_SESSION['user_uid'], ['Password' => password_hash($new_pass, PASSWORD_DEFAULT), 'ForcePasswordChange' => 0])) {
                    $success_message = "Password updated successfully. Redirecting...";
                    header("refresh:2;url=projects");
                } else {
                    $error_message = "Failed to update password.";
                }
            }
        }
        include 'view-change-password.php';
        break;

    case 'register':
        if (get_app_config('allow_self_registration') !== true) { http_response_code(403); include 'view-registration-disabled.php'; exit; }
        $error_message = '';
        if (isset($_SESSION['google_login_error'])) {
            $error_message = $_SESSION['google_login_error'];
            unset($_SESSION['google_login_error']);
        }
        $success_message = '';
        $show_otp_form = false;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Step 1: Complete Google Signup (No OTP needed)
            if (isset($_POST['complete_google_signup'])) {
                $firstName = trim($_POST['first_name'] ?? '');
                $lastName = trim($_POST['last_name'] ?? '');
                $bornYear = trim($_POST['born_year'] ?? '');
                $email = trim(strtolower($_POST['email'] ?? ''));
                $password = $_POST['password'] ?? '';
                
                // Verify against session to prevent tampering
                if (!isset($_SESSION['google_signup_data']) || $_SESSION['google_signup_data']['email'] !== $email) {
                     $error_message = "Registration session invalid. Please login with Google again.";
                } elseif (empty($firstName) || empty($lastName) || empty($bornYear) || empty($email)) {
                     $error_message = "All fields are required.";
                } else {
                     // Generate a random secure password for the user since they are using Google Login
                     $password = bin2hex(random_bytes(10)); // 20 chars long random password
                     // Create Account Directly
                    $new_uid = generateUID($firstName, $lastName, $bornYear);
                    $user_data = [
                        'UID' => $new_uid, 
                        'FirstName' => $firstName, 
                        'LastName' => $lastName, 
                        'BornYear' => $bornYear,
                        'Email' => $email, 
                        'Password' => password_hash($password, PASSWORD_DEFAULT),
                        'Status' => 'active', 
                        'ResetToken' => '', 
                        'TokenExpiry' => ''
                    ];
                    
                    if (writeUserDetailsCSV($user_data)) {
                        initUserDataCSV($new_uid); 
                        save_dashboard_layout($new_uid, []);
                        
                        // Auto-login
                        $_SESSION['user_uid'] = $new_uid;
                        unset($_SESSION['google_signup_data']);
                        
                        $email_body = "<h1>Welcome to the Ninja IoT!</h1><p>Your account has been created via Google Login.</p>";
                        send_email($email, "Account Created Successfully", $email_body);
                        
                        header('Location: projects'); 
                        exit;
                    } else { 
                        $error_message = "Failed to create account. Please try again later."; 
                    }
                }
            }
            // Step 1: Send OTP (Regular Flow)
            elseif (isset($_POST['send_otp'])) {
                $firstName = trim($_POST['first_name'] ?? ''); 
                $lastName = trim($_POST['last_name'] ?? ''); 
                $bornYear = trim($_POST['born_year'] ?? '');
                $email = trim(strtolower($_POST['email'] ?? '')); 
                $password = $_POST['password'] ?? '';
                
                if (empty($firstName) || empty($lastName) || empty($bornYear) || empty($email) || empty($password)) { 
                    $error_message = "All fields are required."; 
                }
                elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false || substr($email, -10) !== '@gmail.com') { 
                    $error_message = "A valid Gmail address is required."; 
                }
                elseif (strlen($password) < 8) { 
                    $error_message = "Password must be at least 8 characters long."; 
                }
                elseif (getUserBy('Email', $email)) { 
                    $error_message = "An account with this email already exists."; 
                }
                else {
                    // Generate 4-digit OTP
                    $otp = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Store registration data and OTP in session
                    $_SESSION['registration_data'] = [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'born_year' => $bornYear,
                        'email' => $email,
                        'password' => $password,
                        'otp' => $otp,
                        'otp_expiry' => time() + 600 // 10 minutes
                    ];
                    
                    // Send OTP email
                    $email_body = "<h1>Email Verification</h1><p>Hello ".htmlspecialchars($firstName).",</p><p>Your OTP for registration is: <strong style='font-size: 24px; color: #3498db;'>$otp</strong></p><p>This OTP is valid for 10 minutes.</p><p>If you did not request this, please ignore this email.</p>";
                    
                    if (send_email($email, "Your Registration OTP", $email_body)) {
                        $success_message = "OTP has been sent to your email. Please check your inbox.";
                        $show_otp_form = true;
                    } else {
                        $error_message = "Failed to send OTP. Please check your email configuration.";
                    }
                }
            }
            // Step 2: Verify OTP and Create Account
            elseif (isset($_POST['verify_otp'])) {
                $entered_otp = trim($_POST['otp'] ?? '');
                
                if (empty($entered_otp)) {
                    $error_message = "Please enter the OTP.";
                    $show_otp_form = true;
                }
                elseif (!isset($_SESSION['registration_data'])) {
                    $error_message = "Session expired. Please start registration again.";
                }
                elseif (time() > $_SESSION['registration_data']['otp_expiry']) {
                    $error_message = "OTP has expired. Please request a new one.";
                    unset($_SESSION['registration_data']);
                }
                elseif ($entered_otp !== $_SESSION['registration_data']['otp']) {
                    $error_message = "Invalid OTP. Please try again.";
                    $show_otp_form = true;
                }
                else {
                    // OTP verified, create account
                    $reg_data = $_SESSION['registration_data'];
                    $new_uid = generateUID($reg_data['first_name'], $reg_data['last_name'], $reg_data['born_year']);
                    
                    $user_data = [
                        'UID' => $new_uid, 
                        'FirstName' => $reg_data['first_name'], 
                        'LastName' => $reg_data['last_name'], 
                        'BornYear' => $reg_data['born_year'],
                        'Email' => $reg_data['email'], 
                        'Password' => password_hash($reg_data['password'], PASSWORD_DEFAULT),
                        'Status' => 'active', 
                        'ResetToken' => '', 
                        'TokenExpiry' => ''
                    ];
                    
                    if (writeUserDetailsCSV($user_data)) {
                        initUserDataCSV($new_uid); 
                        save_dashboard_layout($new_uid, []);
                        
                        $email_body = "<h1>Welcome to the Ninja IoT!</h1><p>Your account has been created successfully, ".htmlspecialchars($reg_data['first_name']).".</p><p>You can now log in using your email and the password you provided.</p><p>Login URL: <a href='" . APP_BASE_URL . "login'>".APP_BASE_URL."login</a></p>";
                        send_email($reg_data['email'], "Account Created Successfully", $email_body);
                        
                        unset($_SESSION['registration_data']);
                        header('Location: login?registered=1'); 
                        exit;
                    } else { 
                        $error_message = "Failed to create account. Please try again later."; 
                    }
                }
            }
        }
        
        // Check if we should show OTP form from session
        if (isset($_SESSION['registration_data']) && !$show_otp_form && empty($error_message)) {
            $show_otp_form = true;
        }
        
        include 'view-register.php';
        break;

    case 'forgot-password':
        $error_message = ''; $success_message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
            $email = trim(strtolower($_POST['email']));
            $user = getUserBy('Email', $email);
            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
                if (updateUserRecord($user['UID'], ['ResetToken' => $token, 'TokenExpiry' => $expiry])) {
                    $reset_link = rtrim(APP_BASE_URL, '/') . "/reset-password?token=" . $token;
                    $email_body = "<h1>Password Reset Request</h1><p>You requested a password reset for your Ninja IoT account. Click the link below to set a new password. This link is valid for 1 hour.</p><p><a href='$reset_link'>$reset_link</a></p><p>If you did not request this, please ignore this email.</p>";
                    send_email($user['Email'], "Your Password Reset Link", $email_body);
                }
            }
            $success_message = "If an account with that email exists, a password reset link has been sent.";
        }
        include 'view-forgot-password.php';
        break;

    case 'reset-password':
        $error_message = ''; $token = trim($_GET['token'] ?? ''); $is_valid_token = false;
        if (empty($token)) { $error_message = "Invalid or missing reset token."; }
        else {
            $user = getUserBy('ResetToken', $token);
            if ($user && isset($user['TokenExpiry']) && time() < strtotime($user['TokenExpiry'])) {
                $is_valid_token = true;
            } else { $error_message = "This reset link is invalid or has expired."; }
        }
        if ($is_valid_token && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
            $password = $_POST['password'];
            if (strlen($password) < 8) { $error_message = "Password must be at least 8 characters long."; }
            else {
                $updateData = [
                    'Password' => password_hash($password, PASSWORD_DEFAULT),
                    'ResetToken' => '', 'TokenExpiry' => '', 'Status' => 'active'
                ];
                if (updateUserRecord($user['UID'], $updateData)) {
                    header('Location: login?reset_success=1'); exit;
                } else { $error_message = "Failed to update password. Please try again."; }
            }
        }
        include 'view-reset-password.php';
        break;

    case 'force-reset':
        if (!isset($_SESSION['user_uid'])) { header('Location: login'); exit; }
        $user = getUserBy('UID', $_SESSION['user_uid']);
        if (!$user || $user['Status'] !== 'pending_reset') { header('Location: projects'); exit; }
        $error_message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
            $password = $_POST['password'];
            if (strlen($password) < 8) { $error_message = "Password must be at least 8 characters long."; }
            else {
                $updateData = ['Password' => password_hash($password, PASSWORD_DEFAULT), 'Status' => 'active'];
                if (updateUserRecord($user['UID'], $updateData)) {
                    header('Location: projects'); exit;
                } else { $error_message = "Failed to update password. Please try again."; }
            }
        }
        include 'view-force-reset.php';
        break;

    case 'projects':
        if (!isset($_SESSION['user_uid'])) { header('Location: login'); exit; }
        $current_user_uid = $_SESSION['user_uid'];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['create_project'])) {
                $p_name = trim($_POST['project_name'] ?? '');
                $p_board = trim($_POST['board_type'] ?? 'ESP8266');
                
                // Check project limit
                $existing_projects = getProjectsByUser($current_user_uid);
                
                $name_exists = false;
                foreach ($existing_projects as $ep) {
                    if (strtolower($ep['Name']) === strtolower($p_name)) {
                        $name_exists = true; break;
                    }
                }
                
                if (count($existing_projects) >= 10) {
                    $error_message = "You have reached the maximum limit of 10 projects.";
                } elseif (empty($p_name)) {
                    $error_message = "Project name is required.";
                } elseif ($name_exists) {
                    $error_message = "A project with this name already exists.";
                } else {
                    if (createProject($current_user_uid, $p_name, $p_board)) {
                        header('Location: projects'); exit;
                    } else {
                        $error_message = "Failed to create project.";
                    }
                }
            } elseif (isset($_POST['rename_project'])) {
                $p_id = trim($_POST['project_id'] ?? '');
                $new_name = trim($_POST['new_project_name'] ?? '');
                if (!empty($p_id) && !empty($new_name)) {
                    $existing_projects = getProjectsByUser($current_user_uid);
                    $name_exists = false;
                    foreach ($existing_projects as $ep) {
                        if ($ep['ProjectID'] !== $p_id && strtolower($ep['Name']) === strtolower($new_name)) {
                            $name_exists = true; break;
                        }
                    }
                    if ($name_exists) {
                        $error_message = "A project with this name already exists.";
                    } elseif (renameProject($p_id, $new_name, $current_user_uid)) {
                        header('Location: projects'); exit;
                    } else {
                        $error_message = "Failed to rename project.";
                    }
                }
            } elseif (isset($_POST['delete_project'])) {
                $p_id = trim($_POST['project_id'] ?? '');
                if (!empty($p_id)) {
                    if (deleteProject($p_id, $current_user_uid)) {
                        header('Location: projects'); exit;
                    } else {
                        $error_message = "Failed to delete project.";
                    }
                }
            }
        }
        
        $user_projects = getProjectsByUser($current_user_uid);
        include 'view-projects.php';
        break;

    case 'events':
        if (!isset($_SESSION['user_uid'])) { header('Location: login'); exit; }
        $real_user_uid = $_SESSION['user_uid'];
        $project_id = isset($_GET['project_id']) ? trim($_GET['project_id']) : null;
        
        if (!$project_id) { header('Location: projects'); exit; }
        $current_project = getProjectByID($project_id);
        if (!$current_project || $current_project['UID'] !== $real_user_uid) { header('Location: projects'); exit; }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_events'])) {
            $events_json = $_POST['events_json'] ?? '[]';
            $events_xml = $_POST['events_xml'] ?? '';
            // Save JSON for execution engine
            file_put_contents(DATA_DIR . "events_run_" . $project_id . ".json", $events_json);
            // Save XML for UI restoration
            file_put_contents(DATA_DIR . "events_ui_" . $project_id . ".xml", $events_xml);
            echo "success"; exit;
        }

        include 'view-events.php';
        break;

    case 'dashboard':
        if (!isset($_SESSION['user_uid'])) { header('Location: login'); exit; }
        $real_user_uid = $_SESSION['user_uid'];
        
        if (isset($_GET['logout'])) { unset($_SESSION['user_uid']); session_destroy(); header('Location: login'); exit; }

        $project_id = isset($_GET['project_id']) ? trim($_GET['project_id']) : null;
        if (!$project_id) { header('Location: projects'); exit; }

        $current_project = getProjectByID($project_id);
        if (!$current_project || $current_project['UID'] !== $real_user_uid) {
            // Project not found or doesn't belong to user
            header('Location: projects'); exit;
        }

        // Use Project ID as the "UID" for data storage functions
        $current_user_uid = $project_id; 
        
        // Set Pin Definitions based on Board Type
        if ($current_project['BoardType'] === 'ESP32') {
            define('CURRENT_PROJECT_PINS', ESP32_PINS);
        } else {
            define('CURRENT_PROJECT_PINS', ESP8266_PINS);
        }

        $user_components = load_dashboard_layout($current_user_uid);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_component'])) {
            $component_type = $_POST['component_type'] ?? null;
            $source_type_from_form = $_POST['source_type'] ?? 'pin';
            $dataSourceName = '';

            if ($source_type_from_form === 'pin') { $dataSourceName = isset($_POST['pin']) ? trim($_POST['pin']) : ''; }
            elseif ($source_type_from_form === 'variable') {
                $variable_selection = $_POST['variable_selection'] ?? '';
                if ($variable_selection === '_new_') {
                    $raw_variable_name = isset($_POST['new_variable_name']) ? trim($_POST['new_variable_name']) : '';
                    if (empty($raw_variable_name)) { $_SESSION['dashboard_error'] = "New variable name cannot be empty."; $dataSourceName = ''; }
                    else {
                        $dataSourceName = sanitize_custom_variable_name($raw_variable_name);
                        if ($dataSourceName === 'invalid_variable' || empty($dataSourceName)) { $_SESSION['dashboard_error'] = "Invalid custom variable name: " . htmlspecialchars($raw_variable_name); $dataSourceName = ''; }
                    }
                } elseif (!empty($variable_selection)) { $dataSourceName = $variable_selection; }
                else { $_SESSION['dashboard_error'] = "Please select or create a variable."; $dataSourceName = ''; }
            }

            if ($component_type && !empty($dataSourceName)) {
                $component = [ 
                    'type' => $component_type, 
                    'pin' => ($source_type_from_form === 'pin' ? $dataSourceName : ''), 
                    'variable' => ($source_type_from_form === 'variable' ? $dataSourceName : ''), 
                    'display_name' => trim($_POST['display_name'] ?? ''),
                    'id' => 'comp_'.uniqid() 
                ];
                if ($component['type'] === 'toggle') { $component['on_value'] = $_POST['on_value'] ?? '1'; $component['off_value'] = $_POST['off_value'] ?? '0'; }
                elseif ($component['type'] === 'slider') { $component['min_value'] = $_POST['min_value'] ?? '0'; $component['max_value'] = $_POST['max_value'] ?? '255'; }
                elseif (in_array($component['type'], ['gauge', 'textview', 'graph', 'status'])) { 
                    $component['interval'] = max(4, intval($_POST['interval'] ?? 5)); 
                    if ($component['type'] === 'gauge') {
                        $component['gauge_min'] = $_POST['gauge_min'] ?? '0';
                        $component['gauge_max'] = $_POST['gauge_max'] ?? '100';
                        $component['gauge_units'] = $_POST['gauge_units'] ?? '%';
                    }
                }
                if ($component['type'] === 'status') { $component['on_color'] = $_POST['on_color'] ?? '#2ecc71'; $component['off_color'] = $_POST['off_color'] ?? '#e74c3c'; }
                if ($component['type'] === 'graph') { $component['graph_width'] = max(150, min(800, intval($_POST['graph_width'] ?? 300))); $component['graph_height'] = max(100, min(500, intval($_POST['graph_height'] ?? 200)));}
                
                $component_visual_heights = ['toggle' => 80, 'slider' => 100, 'gauge' => 130, 'textview' => 80, 'status' => 80, 'graph' => 250, 'text_input' => 180];
                $estimated_height = $component_visual_heights[$component_type] ?? 150;
                if ($component_type === 'graph' && isset($component['graph_height'])) { $estimated_height = max(100, intval($component['graph_height'])) + 50; }
                $next_y = 20;
                if (!empty($user_components)) {
                    $lowest_y_plus_height = 0;
                    foreach($user_components as $existing_comp) {
                        $existing_comp_type = $existing_comp['type'] ?? 'default'; $existing_height_est = $component_visual_heights[$existing_comp_type] ?? 150;
                        if ($existing_comp_type === 'graph' && isset($existing_comp['graph_height'])) { $existing_height_est = max(100, intval($existing_comp['graph_height'])) + 50; }
                        $current_bottom = (isset($existing_comp['y']) ? intval($existing_comp['y']) : 0) + $existing_height_est;
                        if ($current_bottom > $lowest_y_plus_height) { $lowest_y_plus_height = $current_bottom; }
                    } $next_y = $lowest_y_plus_height + 20;
                }
                $component['x'] = 20; $component['y'] = $next_y;

                if ($source_type_from_form === 'variable' && isset($_POST['variable_selection']) && $_POST['variable_selection'] === '_new_') {
                    if (!writeUserDataCSV($current_user_uid, $dataSourceName, '')) { error_log("Dashboard: Failed to pre-emptively create CSV column for new variable '$dataSourceName' for UID '$current_user_uid'."); }
                }
                $user_components[] = $component; save_dashboard_layout($current_user_uid, $user_components);
                header('Location: dashboard?project_id=' . $project_id); exit;
            } else {
                if (empty($_SESSION['dashboard_error'])) { $_SESSION['dashboard_error'] = "Component type and Data Source are required."; }
                header('Location: dashboard?project_id=' . $project_id); exit;
            }
        }
        
        if (isset($_GET['delete_component'])) {
            $id_to_delete = $_GET['delete_component'];
            $user_components = array_filter($user_components, function($comp) use ($id_to_delete) { return isset($comp['id']) && $comp['id'] !== $id_to_delete; });
            $user_components = array_values($user_components);
            save_dashboard_layout($current_user_uid, $user_components);
            header('Location: dashboard?project_id=' . $project_id); exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_position'])) {
            header('Content-Type: text/plain'); $id = $_POST['id'] ?? null; $x = isset($_POST['x']) ? intval($_POST['x']) : null; $y = isset($_POST['y']) ? intval($_POST['y']) : null; $found = false;
            if ($id !== null && $x !== null && $y !== null) {
                $current_layout = load_dashboard_layout($current_user_uid);
                foreach ($current_layout as &$componentRef) { if (isset($componentRef['id']) && $componentRef['id'] === $id) { $componentRef['x'] = max(0, $x); $componentRef['y'] = max(0, $y); $found = true; break; }}
                if ($found) { save_dashboard_layout($current_user_uid, $current_layout); }
            } echo $found ? "success_position_update" : "error_component_not_found"; exit;
        }

        $PIN_VARIABLES_FOR_UI = defined('CURRENT_PROJECT_PINS') ? CURRENT_PROJECT_PINS : PIN_FIELDS_ARRAY;
        $SUGGESTED_VARIABLES_FOR_UI_DASH = UI_SUGGESTED_VARIABLES;
        $existing_custom_variables = get_user_custom_variables($current_user_uid);
        $variables_for_variable_tab_dropdown = array_unique(array_merge(DEFAULT_DATA_VARIABLES_IN_CSV, $existing_custom_variables));
        sort($variables_for_variable_tab_dropdown);
        $dashboard_error_message = ''; if (isset($_SESSION['dashboard_error'])) { $dashboard_error_message = $_SESSION['dashboard_error']; unset($_SESSION['dashboard_error']); }
        include 'view-dashboard.php';
        break;

    case 'manage-variables':
        if (!isset($_SESSION['user_uid'])) { header('Location: login'); exit; }
        $real_user_uid = $_SESSION['user_uid'];
        
        $project_id = isset($_GET['project_id']) ? trim($_GET['project_id']) : null;
        if (!$project_id) { header('Location: projects'); exit; }

        $current_project = getProjectByID($project_id);
        if (!$current_project || $current_project['UID'] !== $real_user_uid) {
            header('Location: projects'); exit;
        }

        $current_user_uid = $project_id;
        $manage_var_error = '';
        $manage_var_success = '';
        
        // Handle variable rename
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_variable'])) {
            $old_name = trim($_POST['old_name'] ?? '');
            $new_name = trim($_POST['new_name'] ?? '');
            
            if (empty($old_name) || empty($new_name)) {
                $manage_var_error = 'Both old and new variable names are required.';
            } else {
                $result = rename_user_variable($current_user_uid, $old_name, $new_name);
                if ($result['success']) {
                    $manage_var_success = "Variable '$old_name' renamed to '$new_name' successfully!";
                } else {
                    $manage_var_error = $result['error'];
                }
            }
        }
        
        // Handle variable creation
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_variable'])) {
            $variable_name = trim($_POST['variable_name'] ?? '');
            $initial_value = trim($_POST['initial_value'] ?? '');
            
            if (empty($variable_name)) {
                $manage_var_error = 'Variable name is required.';
            } else {
                // Sanitize the variable name
                $sanitized_name = sanitize_custom_variable_name($variable_name);
                
                if ($sanitized_name === 'invalid_variable' || empty($sanitized_name)) {
                    $manage_var_error = 'Invalid variable name. Only letters, numbers, and underscores are allowed.';
                } else {
                    // Check if variable already exists
                    $existing_vars = get_user_custom_variables($current_user_uid);
                    if (in_array($sanitized_name, $existing_vars)) {
                        $manage_var_error = "Variable '$sanitized_name' already exists.";
                    } else {
                        // Create the variable by writing to CSV
                        if (writeUserDataCSV($current_user_uid, $sanitized_name, $initial_value)) {
                            $manage_var_success = "Variable '$sanitized_name' created successfully!";
                        } else {
                            $manage_var_error = 'Failed to create variable. Please try again.';
                        }
                    }
                }
            }
        }
        
        // Handle variable deletion
        if (isset($_GET['delete_variable'])) {
            $variable_to_delete = trim($_GET['delete_variable']);
            
            if (empty($variable_to_delete)) {
                $manage_var_error = 'Variable name is required for deletion.';
            } else {
                $result = delete_user_variable($current_user_uid, $variable_to_delete);
                if ($result['success']) {
                    $manage_var_success = "Variable '$variable_to_delete' deleted successfully!";
                } else {
                    $manage_var_error = $result['error'];
                }
            }
        }
        
        $custom_variables = get_user_custom_variables($current_user_uid);
        include 'view-manage-variables.php';
        break;


        case 'monitor-user':
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) { header('Location: ' . get_admin_page_url()); exit; }
        $user_uid_to_monitor = trim($_GET['uid'] ?? '');
        if (empty($user_uid_to_monitor) || !isValidUID($user_uid_to_monitor)) {
            $_SESSION['admin_message'] = "Error: Invalid or missing User ID for monitoring.";
            header('Location: ' . get_admin_page_url()); exit;
        }
        include 'view-monitor-user.php';
        break;

    case 'edit-user':
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) { header('Location: ' . get_admin_page_url()); exit; }
        $error_message = '';
        
        // Handle the form submission to update a user
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
            $uid = trim($_POST['uid'] ?? '');
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim(strtolower($_POST['email'] ?? ''));
            $bornYear = trim($_POST['born_year'] ?? '');
            $new_password = $_POST['new_password'] ?? '';

            if (empty($uid) || empty($firstName) || empty($lastName) || empty($email) || empty($bornYear)) {
                $error_message = "Required fields (First Name, Last Name, Email, Born Year) cannot be empty.";
                $user_to_edit = getUserBy('UID', $uid); // Re-fetch data to show form again
                include 'view-edit-user.php';
                exit;
            }
            
            $updateData = [
                'FirstName' => $firstName,
                'LastName'  => $lastName,
                'Email'     => $email,
                'BornYear'  => $bornYear
            ];

            if (!empty($new_password)) {
                if (strlen($new_password) < 8) {
                     $error_message = "New password must be at least 8 characters long.";
                     $user_to_edit = getUserBy('UID', $uid);
                     include 'view-edit-user.php';
                     exit;
                }
                $updateData['Password'] = password_hash($new_password, PASSWORD_DEFAULT);
                $updateData['Status'] = 'active'; 
            }

            if (updateUserRecord($uid, $updateData)) {
                $_SESSION['admin_message'] = "User ".htmlspecialchars($uid)." updated successfully.";
                header('Location: ' . get_admin_page_url());
                exit;
            } else {
                $error_message = "Failed to update user record. Please check permissions or data.";
                $user_to_edit = getUserBy('UID', $uid);
                include 'view-edit-user.php';
                exit;
            }
        }

        // Display the form for editing a user
        $user_uid_to_edit = trim($_GET['uid'] ?? '');
        if (empty($user_uid_to_edit)) {
            $_SESSION['admin_message'] = "Error: Missing User ID for editing.";
            header('Location: ' . get_admin_page_url());
            exit;
        }

        $user_to_edit = getUserBy('UID', $user_uid_to_edit);
        if (empty($user_to_edit)) {
            $_SESSION['admin_message'] = "Error: User with ID ".htmlspecialchars($user_uid_to_edit)." not found.";
            header('Location: ' . get_admin_page_url());
            exit;
        }
        include 'view-edit-user.php';
        break;
    
    // Block old admin URL for security
    case 'admin':
        http_response_code(404);
        include 'view-404.php';
        break;

    default:
        // Check if it's the secure admin path
        $secure_admin_path = get_app_config('admin_secret_path');
        if ($current_page === $secure_admin_path) {
            $admin_error_message = ''; $admin_success_message = '';
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
                if (isset($_POST['admin_id'], $_POST['admin_password']) && $_POST['admin_id'] === ADMIN_ID && $_POST['admin_password'] === ADMIN_PASS) {
                    $_SESSION['admin_logged_in'] = true; 
                    header('Location: ' . get_admin_page_url()); 
                    exit;
                } else { $admin_error_message = "Invalid Admin ID or Password."; }
            }
            if (isset($_GET['logout_admin'])) { 
                unset($_SESSION['admin_logged_in']); 
                session_destroy(); 
                header('Location: ' . get_admin_page_url()); 
                exit; 
            }
            
            if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
                
                // Handle Settings Update
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
                    $current_config = get_app_config();
                    $current_config['allow_self_registration'] = isset($_POST['allow_self_registration']);
                    
                    // Update admin secret path if provided
                    if (isset($_POST['admin_secret_path']) && !empty(trim($_POST['admin_secret_path']))) {
                        $new_path = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($_POST['admin_secret_path']));
                        if (!empty($new_path)) {
                            $current_config['admin_secret_path'] = $new_path;
                        }
                    }
                    
                    save_app_config($current_config);
                    $admin_success_message = "Settings updated successfully.";
                }

                // Handle Bulk Import
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_import']) && isset($_FILES['user_csv'])) {
                    if ($_FILES['user_csv']['error'] == UPLOAD_ERR_OK && is_uploaded_file($_FILES['user_csv']['tmp_name'])) {
                        $csvFile = fopen($_FILES['user_csv']['tmp_name'], 'r');
                        fgetcsv($csvFile); // Skip header row
                        $imported_count = 0; $skipped_emails = [];
                        while (($line = fgetcsv($csvFile)) !== FALSE) {
                            $firstName = trim($line[0] ?? ''); $lastName = trim($line[1] ?? '');
                            $email = trim(strtolower($line[2] ?? '')); $bornYear = trim($line[3] ?? '');
                            $providedPassword = trim($line[4] ?? '');
                            
                            if (empty($firstName) || empty($lastName) || empty($email) || empty($bornYear) || !filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
                            if (getUserBy('Email', $email)) { $skipped_emails[] = $email; continue; }
                            $new_uid = generateUID($firstName, $lastName, $bornYear);
                            
                            // Use provided password or generate a unique temporary one
                            if (!empty($providedPassword)) {
                                $default_password = $providedPassword;
                            } else {
                                $default_password = 'Welcome@' . $bornYear . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 4);
                            }
                            $user_data = [
                                'UID' => $new_uid, 'FirstName' => $firstName, 'LastName' => $lastName, 'BornYear' => $bornYear,
                                'Email' => $email, 'Password' => password_hash($default_password, PASSWORD_DEFAULT),
                                'Status' => 'active', 'ResetToken' => '', 'TokenExpiry' => '', 'ForcePasswordChange' => 1
                            ];
                            if (writeUserDetailsCSV($user_data)) {
                                initUserDataCSV($new_uid); save_dashboard_layout($new_uid, []);
                                // $email_body = "<h1>Welcome to the Ninja IoT!</h1><p>An account has been created for you.</p><p><strong>Email:</strong> $email<br><strong>Temporary Password:</strong> $default_password</p><p>Please change your password after logging in.</p><p>Login URL: <a href='" . APP_BASE_URL . "login'>".APP_BASE_URL."login</a></p>";
                                // send_email($email, "Your Ninja IoT Account", $email_body);
                                $imported_count++;
                            }
                        }
                        fclose($csvFile);
                        $admin_success_message = "Imported $imported_count user(s).";
                        if (!empty($skipped_emails)) { $admin_success_message .= " Skipped existing emails: " . implode(', ', $skipped_emails); }
                    } else { $admin_error_message = "Failed to upload CSV file."; }
                }

                // Handle Create User
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
                    $firstName = trim($_POST['first_name'] ?? '');
                    $lastName = trim($_POST['last_name'] ?? '');
                    $email = trim(strtolower($_POST['email'] ?? ''));
                    $bornYear = trim($_POST['born_year'] ?? '');

                    if (empty($firstName) || empty($lastName) || empty($email) || empty($bornYear)) {
                        $admin_error_message = "All fields are required.";
                    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $admin_error_message = "Invalid email address.";
                    } elseif (getUserBy('Email', $email)) {
                        $admin_error_message = "User with this email already exists.";
                    } else {
                        $new_uid = generateUID($firstName, $lastName, $bornYear);
                        // Unique temporary password: Welcome + Year + Random 4 chars
                        $default_password = 'Welcome@' . $bornYear . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 4);
                        $user_data = [
                            'UID' => $new_uid,
                            'FirstName' => $firstName,
                            'LastName' => $lastName,
                            'BornYear' => $bornYear,
                            'Email' => $email,
                            'Password' => password_hash($default_password, PASSWORD_DEFAULT),
                            'Status' => 'active',
                            'ResetToken' => '',
                            'TokenExpiry' => '',
                            'ForcePasswordChange' => 1
                        ];

                        if (writeUserDetailsCSV($user_data)) {
                            // Initialize user data
                            initUserDataCSV($new_uid);
                            save_dashboard_layout($new_uid, []);
                            
                            // Send Welcome Email
                            $email_body = "<h1>Welcome to the Ninja IoT!</h1><p>An account has been created for you.</p><p><strong>Email:</strong> $email<br><strong>Temporary Password:</strong> $default_password</p><p>Please change your password after logging in.</p><p>Login URL: <a href='" . APP_BASE_URL . "login'>" . APP_BASE_URL . "login</a></p>";
                            send_email($email, "Your Ninja IoT Account", $email_body);

                            $admin_success_message = "User created successfully. UID: $new_uid";
                        } else {
                            $admin_error_message = "Failed to create user record.";
                        }
                    }
                }

                // Handle User Deletion
                if (isset($_GET['delete_user'])) {
                    $uid_to_delete = trim($_GET['delete_user']);
                    error_log("Attempting to delete user: " . $uid_to_delete);
                    if (deleteUser($uid_to_delete)) {
                        error_log("User deletion successful for: " . $uid_to_delete);
                        $admin_success_message = "User deleted successfully.";
                    } else {
                        error_log("User deletion FAILED for: " . $uid_to_delete);
                        $admin_error_message = "Failed to delete user.";
                    }
                    header('Location: ' . get_admin_page_url());
                    exit;
                }

                include 'view-admin.php';
            } else {
                include 'view-admin.php';
            }
        } else {
            // Page not found
            http_response_code(404);
            include 'view-404.php';
        }
        break;
}
?>