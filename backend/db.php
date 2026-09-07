<?php
// ─────────────────────────────────────────────
//  Student Planner — Database Configuration
//  Edit these 4 lines to match your MySQL setup
// ─────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');            // your MySQL username
define('DB_PASS', '');                // your MySQL password
define('DB_NAME', 'student_planner'); // database name (see student_planner.sql)

try {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
} catch (\mysqli_sql_exception $e) {
    // PHP 8.1+ makes mysqli throw on connection failure instead of
    // returning false, so without this try/catch the block below never
    // runs and the visitor just sees a blank fatal-error page.
    $conn = false;
    $connectError = $e->getMessage();
}

if (!$conn) {
    http_response_code(500);
    die('<!DOCTYPE html><html><head><title>DB Error</title>
    <style>body{background:#0a1613;color:#ff8080;font-family:monospace;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
    .box{border:1px solid #ff4444;background:rgba(255,0,0,.05);border-radius:12px;padding:30px 40px;max-width:520px;text-align:center}
    h2{color:#ff4444;letter-spacing:4px;margin-bottom:16px}p{color:rgba(255,150,150,.85);font-size:13px;line-height:1.8}
    code{color:#ffb648}</style>
    </head><body><div class="box">
    <h2>DATABASE CONNECTION FAILED</h2>
    <p>Cannot connect to MySQL.<br>
    Open <code>db.php</code> and make sure <code>DB_HOST</code>, <code>DB_USER</code>, <code>DB_PASS</code> and <code>DB_NAME</code> are correct,
    then import <code>student_planner.sql</code> into your database.<br><br>
    MySQL says: ' . htmlspecialchars($connectError ?? mysqli_connect_error()) . '</p>
    </div></body></html>');
}

mysqli_set_charset($conn, 'utf8mb4');
