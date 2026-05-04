<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'book_borrowing_system');

// System configuration
define('SITE_URL', 'http://localhost/book_borrowing');
define('BORROW_PERIOD_DAYS', 14); // 2 weeks borrow period
define('PENALTY_PER_DAY', 5.00); // $5 per day penalty

// Session configuration
session_start();

// Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Helper functions
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function calculate_penalty($due_date, $return_date) {
    $due = new DateTime($due_date);
    $return = new DateTime($return_date);
    
    if ($return <= $due) {
        return 0;
    }
    
    $days_overdue = $return->diff($due)->days;
    return $days_overdue * PENALTY_PER_DAY;
}
?>
