<?php
session_start();

// 1. Set Timezone to India
date_default_timezone_set('Asia/Kolkata');

// Database Constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'donation_app_v2');

// Connect
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// --- GLOBAL: CHECK FOR SINGLE ORGANIZATION ---
$SINGLE_ORG = null;
// Update: Exclude deleted organizations
$org_check = $conn->query("SELECT * FROM organizations WHERE is_deleted = 0 OR is_deleted IS NULL");
if ($org_check->num_rows === 1) {
    $SINGLE_ORG = $org_check->fetch_assoc();
}

// --- SECURITY FUNCTIONS ---

// 2. CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token_field() {
    return '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';
}

function verify_csrf() {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF Validation Failed. Request blocked.");
    }
}

// 3. XSS Sanitization (Strict - No HTML allowed)
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// 4. Clean HTML (Allow Rich Text tags, block Scripts) - NEW FOR EDITOR
function clean_html($string) {
    // Allowed tags for Bank Details & Footer
    $allowed_tags = '<br><p><b><strong><i><em><u><ul><ol><li><span><div><font>';
    return strip_tags($string, $allowed_tags);
}

// --- UTILITIES ---

function formatInr($amount) {
    return '₹' . number_format($amount, 2);
}

// Number to Words (Indian Style - Lakhs/Crores)
function numberToWords($number) {
    $no = floor($number);
    $point = round($number - $no, 2) * 100;
    $digits_1 = strlen($no);
    $i = 0;
    $str = array();
    $words = array('0' => '', '1' => 'One', '2' => 'Two',
        '3' => 'Three', '4' => 'Four', '5' => 'Five', '6' => 'Six',
        '7' => 'Seven', '8' => 'Eight', '9' => 'Nine',
        '10' => 'Ten', '11' => 'Eleven', '12' => 'Twelve',
        '13' => 'Thirteen', '14' => 'Fourteen',
        '15' => 'Fifteen', '16' => 'Sixteen', '17' => 'Seventeen',
        '18' => 'Eighteen', '19' => 'Nineteen', '20' => 'Twenty',
        '30' => 'Thirty', '40' => 'Forty', '50' => 'Fifty',
        '60' => 'Sixty', '70' => 'Seventy',
        '80' => 'Eighty', '90' => 'Ninety');
    $digits = array('', 'Hundred', 'Thousand', 'Lakh', 'Crore');
    
    while ($i < $digits_1) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += ($divider == 10) ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? '' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str [] = ($number < 21) ? $words[$number] . " " . $digits[$counter] . $plural . " " . $hundred :
                $words[floor($number / 10) * 10] . " " . $words[$number % 10] . " " . $digits[$counter] . $plural . " " . $hundred;
        } else $str[] = null;
    }
    $str = array_reverse($str);
    $result = implode('', $str);
    $paise_text = "";
    if ($point > 0) {
        $paise_text = " and " . $words[$point / 10 * 10] . " " . $words[$point % 10] . " Paise";
    }
    return ($result ? $result . " Rupees" : "") . $paise_text . " Only";
}

// Check Login
function require_login() {
    global $conn; 
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    $stmt = $conn->prepare("SELECT is_active, is_deleted, role FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if (!$user || $user['is_deleted'] == 1 || ($user['role'] !== 'admin' && $user['is_active'] == 0)) {
        session_unset();
        session_destroy();
        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('HTTP/1.1 403 Forbidden');
            die("ACCESS DENIED: Your account has been disabled. Please contact Admin.");
        }
        header("Location: login.php?msg=disabled");
        exit();
    }
}
?>