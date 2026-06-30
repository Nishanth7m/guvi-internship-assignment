<?php
/**
 * Diagnostic Script: List Environment Variables
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== ENVIRONMENT VARIABLES DIAGNOSTIC ===\n\n";

// Fetch all env variables from different PHP sources
$env = array_merge($_ENV, $_SERVER);
foreach (getenv() as $key => $value) {
    $env[$key] = $value;
}

ksort($env);

echo "Listing all database and cache related variables:\n";
echo "--------------------------------------------------\n";

$found = false;
foreach ($env as $key => $value) {
    // Look for keys containing MySQL, Redis, Mongo, DB or Connection strings
    if (preg_match('/(redis|mysql|mongo|db)/i', $key)) {
        $found = true;
        
        // Hide actual values of passwords/URLs but show their length & type
        if (preg_match('/(pass|auth|uri|url|pwd|key|secret)/i', $key)) {
            $maskedValue = "**** (Length: " . strlen($value) . ")";
        } else {
            $maskedValue = $value;
        }
        
        echo "$key: $maskedValue\n";
    }
}

if (!$found) {
    echo "No database or cache related variables found in the environment.\n";
}
