<?php
// functions.php - Core functions and constants

require_once 'db.php';

// --- PHPMailer Integration ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

// --- Constants ---
define('ESP8266_PINS', ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'D6', 'D7', 'D8', 'A0', 'RX', 'TX']);
define('ESP32_PINS', ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'D6', 'D7', 'D8', 'D9', 'D10', 'D12', 'D13', 'D14', 'D15', 'D16', 'D17', 'D18', 'D19', 'D21', 'D22', 'D23', 'D25', 'D26', 'D27', 'D32', 'D33', 'D34', 'D35', 'VP', 'VN']);
define('PIN_FIELDS_ARRAY', ESP8266_PINS); // Default fallback
define('DEFAULT_DATA_VARIABLES_IN_CSV', ['Temperature', 'Humidity']);
define('DATA_FIELDS_ALLOWED_ARRAY', array_merge(PIN_FIELDS_ARRAY, DEFAULT_DATA_VARIABLES_IN_CSV));
define('UI_SUGGESTED_VARIABLES', ['Temperature', 'Humidity']);
define('USER_DETAILS_FIELDS', ['UID', 'FirstName', 'LastName', 'BornYear', 'Email', 'Password', 'Status', 'ResetToken', 'TokenExpiry', 'ForcePasswordChange']);

function verifyGoogleToken($id_token) {
    if (empty($id_token)) return false;
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token;
    $response = @file_get_contents($url);
    if ($response === false) return false;
    $data = json_decode($response, true);
    if (isset($data['aud']) && $data['aud'] === GOOGLE_CLIENT_ID) {
        return $data;
    }
    return false;
}


// --- Utility & Core Functions ---
function get_app_config($key = null) {
    $config = json_decode(@file_get_contents(DATA_DIR . 'config.json'), true);
    if (json_last_error() !== JSON_ERROR_NONE) return $key ? null : []; // Return default if JSON is corrupt
    if ($key) return $config[$key] ?? null;
    return is_array($config) ? $config : [];
}

function save_app_config($new_config) {
    $configFile = DATA_DIR . 'config.json';
    file_put_contents($configFile, json_encode($new_config, JSON_PRETTY_PRINT), LOCK_EX);
}

function get_admin_page_url() {
    return urlencode(get_app_config('admin_secret_path') ?? 'admin');
}

function send_email($recipient_email, $subject, $body) {
    if (empty(SMTP_USERNAME) || empty(SMTP_PASSWORD) || SMTP_USERNAME === 'yourgmail@gmail.com') {
        error_log("Email not sent to '$recipient_email': SMTP credentials are not configured in config.php.");
        return false;
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465; // Default for SMTPS

        // Override for TLS if specified
        if(strcasecmp(SMTP_SECURE, 'tls') == 0) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
        }

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($recipient_email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = nl2br($body);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error for recipient '$recipient_email': {$mail->ErrorInfo}");
        return false;
    }
}

function sanitize_filename_component($name) { return preg_replace("/[^a-zA-Z0-9_.-]/", "_", $name); }
function sanitize_custom_variable_name($name) {
    $name = strtolower($name); // Standardize to lowercase
    $name = preg_replace("/[^a-z0-9_]/", "_", $name); 
    $name = preg_replace('/_+/', '_', $name);
    $name = trim($name, '_'); 
    if (empty($name)) { return 'invalid_variable'; }
    if (is_numeric(substr($name, 0, 1))) { $name = 'var_' . $name; } 
    return $name;
}
function escape_csv_field($field) { return '"' . str_replace('"', '""', (string)$field) . '"'; }

function isValidUID($uid) {
    if (empty($uid)) return false;
    if (!empty(getUserBy('UID', $uid))) return true;
    // Check if it's a project ID
    if (strpos($uid, 'proj_') === 0) {
        return !empty(getProjectByID($uid));
    }
    return false;
}

// --- Project Management Functions (MIGRATED TO SQLITE) ---
function initProjectsCSV() { return true; } // No-op for backwards compat if called

function generateAPIKey() {
    return substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 8);
}

function createProject($uid, $name, $boardType) {
    $db = getDB();
    $projectID = 'proj_' . uniqid();
    $apiKey = generateAPIKey();
    
    // Ensure unique API Key
    while (getProjectByAPIKey($apiKey)) {
        $apiKey = generateAPIKey();
    }

    $stmt = $db->prepare("INSERT INTO projects (ProjectID, UID, Name, BoardType, APIKey) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$projectID, $uid, $name, $boardType, $apiKey])) {
        save_dashboard_layout($projectID, []);
        return $projectID;
    }
    return false;
}

function getProjectsByUser($uid) {
    $stmt = getDB()->prepare("SELECT * FROM projects WHERE UID = ? ORDER BY CreatedAt DESC");
    $stmt->execute([$uid]);
    return $stmt->fetchAll();
}

function getProjectByID($projectID) {
    $stmt = getDB()->prepare("SELECT * FROM projects WHERE ProjectID = ? LIMIT 1");
    $stmt->execute([$projectID]);
    return $stmt->fetch() ?: null;
}

function getProjectByAPIKey($apiKey) {
    $stmt = getDB()->prepare("SELECT * FROM projects WHERE APIKey = ? LIMIT 1");
    $stmt->execute([$apiKey]);
    return $stmt->fetch() ?: null;
}

function renameProject($projectID, $newName, $uid) {
    $stmt = getDB()->prepare("UPDATE projects SET Name = ? WHERE ProjectID = ? AND UID = ?");
    $stmt->execute([$newName, $projectID, $uid]);
    return $stmt->rowCount() > 0;
}

function deleteProject($projectID, $uid) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM projects WHERE ProjectID = ? AND UID = ?");
    $stmt->execute([$projectID, $uid]);
    if ($stmt->rowCount() > 0) {
        $db->prepare("DELETE FROM iot_latest WHERE project_id = ?")->execute([$projectID]);
        $db->prepare("DELETE FROM iot_history WHERE project_id = ?")->execute([$projectID]);
        
        // Clean up any stray associated JSON/XML files
        $safe_pid = sanitize_filename_component($projectID);
        @unlink(DATA_DIR . "dashboard_$safe_pid.json");
        @unlink(DATA_DIR . "events_run_$safe_pid.json");
        @unlink(DATA_DIR . "events_state_$safe_pid.json");
        @unlink(DATA_DIR . "events_ui_$safe_pid.xml");
        return true;
    }
    return false;
}

function deleteUser($uid) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM users WHERE UID = ?");
    $stmt->execute([$uid]);
    if ($stmt->rowCount() > 0) {
        // Delete user's projects
        $userProjects = getProjectsByUser($uid);
        foreach ($userProjects as $proj) {
            deleteProject($proj['ProjectID'], $uid);
        }
        
        // Delete user's dashboard layout
        $safe_uid = sanitize_filename_component($uid);
        @unlink(DATA_DIR . "dashboard_$safe_uid.json");
        return true;
    }
    return false;
}

// --- Dashboard Persistence Functions ---
function load_dashboard_layout($uid) {
    $safe_uid = sanitize_filename_component($uid); $layoutFile = DATA_DIR . "dashboard_" . $safe_uid . ".json";
    if (file_exists($layoutFile)) { $jsonData = file_get_contents($layoutFile); if ($jsonData === false) { error_log("Failed to read dashboard layout file for UID: $safe_uid"); return []; } $layout = json_decode($jsonData, true); if (json_last_error() !== JSON_ERROR_NONE) { error_log("Failed to decode JSON from dashboard layout file for UID: $safe_uid. Error: " . json_last_error_msg()); return []; } return is_array($layout) ? $layout : []; } return [];
}
function save_dashboard_layout($uid, $components_array) {
    $safe_uid = sanitize_filename_component($uid); $layoutFile = DATA_DIR . "dashboard_" . $safe_uid . ".json"; $jsonData = json_encode($components_array, JSON_PRETTY_PRINT); if ($jsonData === false) { error_log("Failed to encode dashboard layout to JSON for UID: $safe_uid. Error: " . json_last_error_msg()); return false; } if (file_put_contents($layoutFile, $jsonData, LOCK_EX) === false) { error_log("Failed to write dashboard layout file for UID: $safe_uid"); return false; } return true;
}

// --- User SQLite Management Functions ---
function initUserDetailsCSV() { return true; }

function getAllUsers() {
    $db = getDB();
    return $db->query("SELECT * FROM users")->fetchAll();
}

function getUserBy($field, $value) {
    if (!in_array($field, USER_DETAILS_FIELDS)) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE $field = ? LIMIT 1");
    $stmt->execute([$value]);
    return $stmt->fetch() ?: null;
}

function writeUserDetailsCSV($user_data) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO users (UID, FirstName, LastName, BornYear, Email, Password, Status, ResetToken, TokenExpiry, ForcePasswordChange) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([
        $user_data['UID'] ?? '',
        $user_data['FirstName'] ?? '',
        $user_data['LastName'] ?? '',
        $user_data['BornYear'] ?? '',
        $user_data['Email'] ?? '',
        $user_data['Password'] ?? '',
        $user_data['Status'] ?? 'active',
        $user_data['ResetToken'] ?? '',
        $user_data['TokenExpiry'] ?? '',
        $user_data['ForcePasswordChange'] ?? 0
    ]);
}

function updateUserRecord($uid, $updateData) {
    if (empty($updateData)) return false;
    $db = getDB();
    $setClause = [];
    $params = [];
    foreach ($updateData as $key => $value) {
        if (in_array($key, USER_DETAILS_FIELDS) && $key !== 'UID') {
            $setClause[] = "$key = ?";
            $params[] = $value;
        }
    }
    if (empty($setClause)) return false;
    $params[] = $uid;
    $stmt = $db->prepare("UPDATE users SET " . implode(', ', $setClause) . " WHERE UID = ?");
    return $stmt->execute($params);
}
// --- User Data SQLite Functions ---
function initUserDataCSV($uid) { return true; } // No-op

function readUserDataCSV($uid, $field_filter = null, $history = false) {
    $db = getDB();
    if ($history) {
        if ($field_filter === null) return [];
        $stmt = $db->prepare("SELECT recorded_at as Timestamp, field as Field, value as Value FROM iot_history WHERE project_id = ? AND field = ? ORDER BY recorded_at DESC LIMIT 60");
        $stmt->execute([$uid, $field_filter]);
        $res = $stmt->fetchAll();
        return array_reverse($res);
    } else {
        if ($field_filter !== null) {
            $stmt = $db->prepare("SELECT value FROM iot_latest WHERE project_id = ? AND field = ?");
            $stmt->execute([$uid, $field_filter]);
            $val = $stmt->fetchColumn();
            return [$field_filter => $val !== false ? $val : ''];
        } else {
            $stmt = $db->prepare("SELECT field, value FROM iot_latest WHERE project_id = ?");
            $stmt->execute([$uid]);
            $data = [];
            while ($row = $stmt->fetch()) {
                $data[$row['field']] = $row['value'];
            }
            return $data;
        }
    }
}

function readUserHistoryMultipleFields($uid, $fields_arr) {
    if (empty($fields_arr)) return [];
    $db = getDB();
    $placeholders = implode(',', array_fill(0, count($fields_arr), '?'));
    $params = array_merge([$uid], $fields_arr);
    
    $stmt = $db->prepare("SELECT recorded_at as Timestamp, field, value FROM iot_history WHERE project_id = ? AND field IN ($placeholders) ORDER BY recorded_at DESC LIMIT 1000");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    
    // Pivot data by timestamp
    $grouped = [];
    foreach ($rows as $row) {
        $ts = $row['Timestamp'];
        if (!isset($grouped[$ts])) {
            $grouped[$ts] = ['Timestamp' => $ts];
        }
        $grouped[$ts][$row['field']] = $row['value'];
    }
    
    $processedHistory = array_values($grouped);
    // Return oldest to newest, up to 100 entries
    $processedHistory = array_slice($processedHistory, 0, 100);
    return array_reverse($processedHistory);
}

function readAllUsersDataCSV() {
    $db = getDB();
    // Reconstruct latest data per project
    $stmt = $db->query("SELECT project_id as UID, field, value FROM iot_latest ORDER BY project_id");
    $data = [];
    $currentProject = null;
    $currentRow = [];
    
    while ($row = $stmt->fetch()) {
        if ($row['UID'] !== $currentProject) {
            if ($currentProject !== null) {
                $data[] = $currentRow;
            }
            $currentProject = $row['UID'];
            $currentRow = ['UID' => $currentProject];
        }
        $currentRow[$row['field']] = $row['value'];
    }
    if ($currentProject !== null) {
        $data[] = $currentRow;
    }
    return $data;
}
function writeUserDataCSV($uid, $field, $value) {
    $safe_uid = sanitize_filename_component($uid);
    if (!in_array($field, PIN_FIELDS_ARRAY) && !in_array($field, DEFAULT_DATA_VARIABLES_IN_CSV)) {
        $field = sanitize_custom_variable_name($field); 
        if ($field === 'invalid_variable') { 
            error_log("Invalid custom variable name after sanitization for UID $safe_uid."); 
            return false; 
        }
    }
    
    $db = getDB();
    $timestamp = date('Y-m-d H:i:s');
    
    $db->beginTransaction();
    try {
        // Get old value to see if it changed
        $stmtOld = $db->prepare("SELECT value FROM iot_latest WHERE project_id = ? AND field = ?");
        $stmtOld->execute([$uid, $field]);
        $oldValue = $stmtOld->fetchColumn();
        
        // 1. Insert/Update LATEST value
        $stmtLatest = $db->prepare("INSERT OR REPLACE INTO iot_latest (project_id, field, value, updated_at) VALUES (?, ?, ?, ?)");
        $stmtLatest->execute([$uid, $field, $value, $timestamp]);
        
        // 2. Append to HISTORY
        $stmtHistory = $db->prepare("INSERT INTO iot_history (project_id, field, value, recorded_at) VALUES (?, ?, ?, ?)");
        $stmtHistory->execute([$uid, $field, $value, $timestamp]);
        
        $db->commit();
        
        // 3. Trigger events
        // Voice commands ALWAYS trigger events (to allow repeating the same command)
        // Other fields trigger ONLY if the value actually changed
        if ($field === 'voicecommand' || (string)$oldValue !== (string)$value) {
            runProjectEvents($uid, $field, $value, $oldValue);
        }
        
        // Notify WebSocket Server
        notifyWebSocketServer($uid, $field, $value);
        
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Failed to write SQLite data for $uid: " . $e->getMessage());
        return false;
    }
}

function notifyWebSocketServer($uid, $field, $value) {
    $data = [
        'uid' => $uid,
        'field' => $field,
        'value' => $value
    ];
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'timeout' => 0.5 // Fast timeout
        ]
    ];
    $context  = stream_context_create($options);
    @file_get_contents('http://127.0.0.1:8080/update', false, $context);
}

// --- Event Engine Helper Functions ---
function resolve_iot_value($val, $projectID) {
    // If it's a JSON object (array in PHP), evaluate it
    if (is_array($val)) {
        if (isset($val['type'])) {
            switch ($val['type']) {
                case 'math':
                    $v1 = resolve_iot_value($val['val1'] ?? 0, $projectID);
                    $v2 = resolve_iot_value($val['val2'] ?? 0, $projectID);
                    $op = $val['op'] ?? 'ADD';
                    if (!is_numeric($v1)) $v1 = 0;
                    if (!is_numeric($v2)) $v2 = 0;
                    switch ($op) {
                        case 'ADD': return $v1 + $v2;
                        case 'SUB': return $v1 - $v2;
                        case 'MUL': return $v1 * $v2;
                        case 'DIV': return ($v2 != 0) ? $v1 / $v2 : 0;
                        case 'MOD': return ($v2 != 0) ? $v1 % $v2 : 0;
                    }
                    break;
                case 'join':
                    $parts = $val['parts'] ?? [];
                    $str = '';
                    foreach ($parts as $p) $str .= resolve_iot_value($p, $projectID);
                    return $str;
                case 'convert':
                    $v = resolve_iot_value($val['value'] ?? '', $projectID);
                    $target = $val['to'] ?? 'string';
                    switch ($target) {
                        case 'number': return is_numeric($v) ? floatval($v) : 0;
                        case 'string': return (string)$v;
                        case 'boolean': return filter_var($v, FILTER_VALIDATE_BOOLEAN);
                    }
                    break;
                case 'get_var':
                    $varName = $val['name'] ?? '';
                    $cleanVarName = sanitize_custom_variable_name($varName);
                    $data = readUserDataCSV($projectID, $cleanVarName);
                    return $data[$cleanVarName] ?? '';
            }
        }
        return $val;
    }

    // Handle Primitives
    $cleanVal = is_string($val) ? trim($val, '"\'') : $val;
    if ($cleanVal === '_CURRENT_TIME_') return date('H:i');
    if ($cleanVal === '_CURRENT_DATE_') return date('Y-m-d');
    return $cleanVal;
}

function execute_iot_actions($actions, $projectID) {
    foreach ($actions as $action) {
        $type = $action['type'] ?? '';
        
        if ($type === 'if') {
            $condition = resolve_iot_value($action['condition'], $projectID);
            // Loose equality for boolean check
            if ($condition == true && $condition !== 'false' && $condition !== '0') {
                execute_iot_actions($action['then'] ?? [], $projectID);
            } else {
                execute_iot_actions($action['else'] ?? [], $projectID);
            }
        } elseif ($type === 'repeat') {
            $count = intval(resolve_iot_value($action['count'], $projectID));
            if ($count > 20) $count = 20; // Safety limit
            for ($i = 0; $i < $count; $i++) {
                execute_iot_actions($action['do'] ?? [], $projectID);
            }
        } elseif ($type === 'wait') {
            $sec = intval(resolve_iot_value($action['seconds'], $projectID));
            if ($sec > 10) $sec = 10; // Safety limit for web context
            if ($sec > 0) sleep($sec);
        } elseif ($type === 'email') {
            $to = resolve_iot_value($action['to'], $projectID);
            $subject = resolve_iot_value($action['subject'], $projectID);
            $body = resolve_iot_value($action['body'], $projectID);
            $smtp_user = $action['smtp_user'];
            $smtp_pass = $action['smtp_pass'];
            
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = $smtp_user;
                $mail->Password = $smtp_pass;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->setFrom($smtp_user, 'IoT Event');
                $mail->addAddress($to);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = nl2br($body);
                $mail->send();
            } catch (Exception $e) {
                error_log("Event Email Failed: " . $mail->ErrorInfo);
            }
        } elseif ($type === 'set_pin') {
            $pin = $action['pin'];
            $val = resolve_iot_value($action['value'], $projectID);
            if ($val === true) $val = 1;
            if ($val === false) $val = 0;
            writeUserDataCSV($projectID, $pin, $val);
        } elseif ($type === 'set_variable') {
            $var = $action['variable'];
            $val = resolve_iot_value($action['value'], $projectID);
            if ($val === true) $val = 1;
            if ($val === false) $val = 0;
            writeUserDataCSV($projectID, $var, $val);
        } elseif ($type === 'http_request') {
            $method = $action['method'];
            $url = resolve_iot_value($action['url'], $projectID);
            $body = resolve_iot_value($action['body'] ?? '', $projectID);
            $saveTo = $action['save_to'] ?? '';

            $options = [
                'http' => [
                    'method'  => $method,
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'content' => $body,
                    'ignore_errors' => true
                ]
            ];
            $context  = stream_context_create($options);
            $result = @file_get_contents($url, false, $context);
            
            if ($result !== false && !empty($saveTo)) {
                writeUserDataCSV($projectID, $saveTo, $result);
            }
        }
    }
}

// --- Event Engine (Triggered on Data Write) ---
function runProjectEvents($projectID, $triggeringField = null, $triggeringValue = null, $oldValue = null) {
    static $eventQueue = [];
    static $isRunning = false;

    // Push current trigger to queue
    $eventQueue[] = [
        'projectID' => $projectID,
        'field' => $triggeringField,
        'value' => $triggeringValue,
        'oldValue' => $oldValue
    ];

    if ($isRunning) {
        return;
    }
    
    $isRunning = true;
    $loopCount = 0;

    // Recursive Value Resolver removed (using global resolve_iot_value)

    while (!empty($eventQueue)) {
        $loopCount++;
        if ($loopCount > 50) {
            error_log("Event Engine: Max event queue depth reached.");
            $eventQueue = [];
            break;
        }

        $event = array_shift($eventQueue);
        $currentProjectID = $event['projectID'];
        $currentField = $event['field'];

        $eventFile = DATA_DIR . "events_run_" . $currentProjectID . ".json";
        if (!file_exists($eventFile)) continue;
        
        $json = file_get_contents($eventFile);
        $rules = json_decode($json, true);
        if (!is_array($rules)) continue;

        // Load Rule State
        $stateFile = DATA_DIR . "events_state_" . $currentProjectID . ".json";
        $ruleStates = [];
        if (file_exists($stateFile)) {
            $ruleStates = json_decode(file_get_contents($stateFile), true) ?? [];
        }
        
        // Read fresh data
        $freshDataRaw = readUserDataCSV($currentProjectID);
        $currentData = [];
        foreach ($freshDataRaw as $k => $v) {
            $currentData[strtolower($k)] = $v;
        }
        
        $stateChanged = false;

        foreach ($rules as $index => $rule) {
            if (!isset($rule['type']) || $rule['type'] !== 'rule') continue;
            
            $trigger = $rule['trigger'];
            $actions = $rule['actions'];
            $isConditionMet = false;
            
            // --- Evaluate Trigger ---
            if ($trigger['type'] === 'compare') {
                $val1 = resolve_iot_value($trigger['val1'], $currentProjectID);
                $val2 = resolve_iot_value($trigger['val2'], $currentProjectID);
                $op = $trigger['op'];

                if (is_numeric($val1) && is_numeric($val2)) {
                    $val1 = floatval($val1);
                    $val2 = floatval($val2);
                }

                if ($op === 'EQ') $isConditionMet = ($val1 == $val2);
                elseif ($op === 'GT') $isConditionMet = ($val1 > $val2);
                elseif ($op === 'LT') $isConditionMet = ($val1 < $val2);
                elseif ($op === 'NEQ') $isConditionMet = ($val1 != $val2);

            } elseif ($trigger['type'] === 'pin_compare' || $trigger['type'] === 'var_compare') {
                $fieldKey = ($trigger['type'] === 'pin_compare') ? 'pin' : 'variable';
                $ruleField = $trigger[$fieldKey] ?? '';
                
                // --- RELEVANCE FILTER ---
                // Only evaluate if the field that changed is the one this rule watches
                $cleanRuleField = ($trigger['type'] === 'var_compare') ? sanitize_custom_variable_name($ruleField) : $ruleField;
                if (strtolower((string)$currentField) !== strtolower((string)$cleanRuleField)) continue;

                $op = $trigger['op'];
                $targetVal = resolve_iot_value($trigger['value'], $currentProjectID);
                $currentVal = $currentData[strtolower((string)$cleanRuleField)] ?? '';

                if ($currentVal !== '' || $currentVal === 0 || $currentVal === '0') {
                    if (is_numeric($currentVal) && is_numeric($targetVal)) {
                        $currentVal = floatval($currentVal);
                        $targetVal = floatval($targetVal);
                    }
                    if ($op === 'EQ')  $isConditionMet = ($currentVal == $targetVal);
                    elseif ($op === 'GT')  $isConditionMet = ($currentVal > $targetVal);
                    elseif ($op === 'LT')  $isConditionMet = ($currentVal < $targetVal);
                    elseif ($op === 'NEQ') $isConditionMet = ($currentVal != $targetVal);
                }
            } elseif ($trigger['type'] === 'voice_command') {
                if (strtolower($currentField) === 'voicecommand') {
                    $op = $trigger['op'];
                    $targetVal = resolve_iot_value($trigger['value'], $currentProjectID);
                    $currentVal = $currentData['voicecommand'] ?? '';
                    if ($currentVal !== '') {
                        $targetValStr = strtolower(trim((string)$targetVal));
                        $currentValStr = strtolower(trim((string)$currentVal));
                        if ($op === 'EQ') {
                            $isConditionMet = ($currentValStr === $targetValStr);
                        } elseif ($op === 'CONTAINS') {
                            $isConditionMet = (strpos($currentValStr, $targetValStr) !== false);
                        }
                    }
                }
            }
            
            // --- State Tracking & Execution ---
            $previousState = $ruleStates[$index] ?? false;
            
            // Only trigger if condition is met AND (it wasn't met before OR the value itself changed)
            // This ensures we enforce the state if the user manually changed a pin, 
            // but still avoids infinite loops.
            if ($isConditionMet) {
                execute_iot_actions($actions, $currentProjectID);
            }

            // Update state
            if ($previousState !== $isConditionMet) {
                $ruleStates[$index] = $isConditionMet;
                $stateChanged = true;
            }
        }

        // Save updated state
        if ($stateChanged) {
            file_put_contents($stateFile, json_encode($ruleStates, JSON_PRETTY_PRINT), LOCK_EX);
        }

        // Auto-clear VoiceCommand so it can trigger edge-detection again next time
        if ($currentField === 'VoiceCommand') {
            $currentVal = $currentData['VoiceCommand'] ?? '';
            if ($currentVal !== '') {
                writeUserDataCSV($currentProjectID, 'VoiceCommand', '');
            }
        }
    }
    
    $isRunning = false;
}

// --- OTHER HELPER FUNCTIONS ---
function generateUID($firstName, $lastName, $bornYear) {
    $firstChar = strtoupper(substr(preg_replace("/[^a-zA-Z]/", "", $firstName), 0, 1));
    $lastChar = strtoupper(substr(preg_replace("/[^a-zA-Z]/", "", $lastName), 0, 1));
    $yearDigits = substr(preg_replace("/[^0-9]/", "", (string)$bornYear), -2);
    $baseUid = ($firstChar ?: 'X') . ($lastChar ?: 'X') . ($yearDigits ?: '00');
    $all_users = getAllUsers();
    $uids_taken = empty($all_users) ? [] : array_column($all_users, 'UID');
    $uid = $baseUid; $counter = 1; while(in_array($uid, $uids_taken)){ $uid = $baseUid . $counter; $counter++; } return $uid;
}
function get_user_custom_variables($uid) {
    $db = getDB();
    $stmt = $db->prepare("SELECT DISTINCT field FROM iot_latest WHERE project_id = ?");
    $stmt->execute([$uid]);
    $fields = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $custom_vars = [];
    $system_fields = array_merge(PIN_FIELDS_ARRAY, DEFAULT_DATA_VARIABLES_IN_CSV);
    foreach ($fields as $field) {
        if (!in_array($field, $system_fields, true) && !empty(trim($field))) {
            $custom_vars[] = $field;
        }
    }
    sort($custom_vars);
    return $custom_vars;
}

function rename_user_variable($uid, $old_name, $new_name) {
    $new_name = sanitize_custom_variable_name($new_name);
    if ($new_name === 'invalid_variable' || empty($new_name)) return ['success' => false, 'error' => 'Invalid new variable name'];
    
    $custom_vars = get_user_custom_variables($uid);
    if (!in_array($old_name, $custom_vars)) return ['success' => false, 'error' => 'Variable does not exist'];
    if (in_array($new_name, $custom_vars) && $old_name !== $new_name) return ['success' => false, 'error' => 'A variable with this name already exists'];
    if ($old_name === $new_name) return ['success' => true];
    
    $db = getDB();
    $db->beginTransaction();
    try {
        // Update history
        $stmtHistory = $db->prepare("UPDATE iot_history SET field = ? WHERE project_id = ? AND field = ?");
        $stmtHistory->execute([$new_name, $uid, $old_name]);
        
        // Update latest
        $stmtLatest = $db->prepare("UPDATE iot_latest SET field = ? WHERE project_id = ? AND field = ?");
        $stmtLatest->execute([$new_name, $uid, $old_name]);
        
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => 'Database error during rename'];
    }
    
    // Update dashboard components
    $safe_uid = sanitize_filename_component($uid);
    $dashboardFile = DATA_DIR . "dashboard_" . $safe_uid . ".json";
    if (file_exists($dashboardFile)) {
        $components = json_decode(file_get_contents($dashboardFile), true);
        if (is_array($components)) {
            foreach ($components as &$component) {
                if (isset($component['variable']) && $component['variable'] === $old_name) {
                    $component['variable'] = $new_name;
                }
            }
            file_put_contents($dashboardFile, json_encode($components, JSON_PRETTY_PRINT), LOCK_EX);
        }
    }
    return ['success' => true];
}

function delete_user_variable($uid, $variable_name) {
    $custom_vars = get_user_custom_variables($uid);
    if (!in_array($variable_name, $custom_vars)) return ['success' => false, 'error' => 'Variable does not exist'];
    
    $db = getDB();
    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM iot_history WHERE project_id = ? AND field = ?")->execute([$uid, $variable_name]);
        $db->prepare("DELETE FROM iot_latest WHERE project_id = ? AND field = ?")->execute([$uid, $variable_name]);
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => 'Database error during deletion'];
    }
    
    // Remove dashboard components using this variable
    $safe_uid = sanitize_filename_component($uid);
    $dashboardFile = DATA_DIR . "dashboard_" . $safe_uid . ".json";
    if (file_exists($dashboardFile)) {
        $components = json_decode(file_get_contents($dashboardFile), true);
        if (is_array($components)) {
            $components = array_filter($components, function($component) use ($variable_name) {
                return !(isset($component['variable']) && $component['variable'] === $variable_name);
            });
            $components = array_values($components); // Re-index
            file_put_contents($dashboardFile, json_encode($components, JSON_PRETTY_PRINT), LOCK_EX);
        }
    }
    return ['success' => true];
}

function stream_csv_directly($filename_base, $uid_for_filename, $callback_populate_csv) {
    $safe_uid_fn = sanitize_filename_component($uid_for_filename);
    header('Content-Description: File Transfer'); header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename_base . $safe_uid_fn . '.csv"');
    header('Expires: 0'); header('Cache-Control: must-revalidate, post-check=0, pre-check=0'); header('Pragma: public');
    $outputHandle = fopen('php://output', 'w');
    if ($outputHandle === false) { error_log("Failed to open php://output for CSV streaming."); http_response_code(500); echo "Error: Could not open output stream."; exit; }
    $callback_populate_csv($outputHandle); fclose($outputHandle); exit;
}
