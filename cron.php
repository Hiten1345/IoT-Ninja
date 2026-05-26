<?php
// cron.php - Run this script every minute via system cron
// * * * * * php /path/to/cron.php

require_once 'config.php';
define('DATA_DIR', 'data/');
require_once 'functions.php'; // Load helper functions

// Disable output buffering
if (ob_get_level()) ob_end_clean();

echo "Starting Event Engine... " . date('Y-m-d H:i:s') . "\n";

$eventFiles = glob(DATA_DIR . "events_run_*.json");

foreach ($eventFiles as $file) {
    $projectID = str_replace(['events_run_', '.json'], '', basename($file));
    echo "Processing Project: $projectID\n";
    
    $json = file_get_contents($file);
    $rules = json_decode($json, true);
    
    if (!is_array($rules)) continue;
    
    foreach ($rules as $ruleIndex => $rule) {
        if (!isset($rule['type']) || $rule['type'] !== 'rule') continue;
        
        $trigger = $rule['trigger'];
        $actions = $rule['actions'];
        $shouldRun = false;
        
        // --- Evaluate Trigger ---
        if ($trigger['type'] === 'timer') {
            $interval = intval($trigger['interval']);
            $currentMinute = intval(date('i'));
            // Simple check: if current minute is divisible by interval
            // Note: This works best if cron runs every minute.
            if ($interval > 0 && ($currentMinute % $interval) === 0) {
                $shouldRun = true;
            }
        } elseif ($trigger['type'] === 'pin_compare') {
            $pin = $trigger['pin'];
            $op = $trigger['op'];
            $targetVal = resolve_iot_value($trigger['value'], $projectID);
            
            // Read current value
            $currentVal = readUserDataCSV($projectID, $pin, false);
            // If result is array (read_all), extract value. If string, it's the value.
            if (is_array($currentVal)) $currentVal = $currentVal[$pin] ?? '';
            
            if ($currentVal !== '') {
                // Compare
                // Auto-detect type
                if (is_numeric($currentVal) && is_numeric($targetVal)) {
                    $currentVal = floatval($currentVal);
                    $targetVal = floatval($targetVal);
                }
                
                if ($op === 'EQ' && $currentVal == $targetVal) $shouldRun = true;
                elseif ($op === 'GT' && $currentVal > $targetVal) $shouldRun = true;
                elseif ($op === 'LT' && $currentVal < $targetVal) $shouldRun = true;
            }
        }
        
        // --- Execute Actions ---
        if ($shouldRun) {
            echo "  Rule $ruleIndex triggered!\n";
            execute_iot_actions($actions, $projectID);
        }
    }
}

echo "Done.\n";
