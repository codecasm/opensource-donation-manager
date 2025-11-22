<?php
include 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { die("Access Denied"); }

$start = $_GET['start'] ?? date('Y-m-d');
$end = $_GET['end'] ?? date('Y-m-d');
$collector = intval($_GET['collector'] ?? 0);
$search = trim($_GET['search'] ?? '');

$where = "WHERE d.created_at BETWEEN '$start 00:00:00' AND '$end 23:59:59'";
if($collector > 0) $where .= " AND d.collected_by = $collector";
if(!empty($search)) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (d.first_name LIKE '%$s%' OR d.last_name LIKE '%$s%' OR d.mobile LIKE '%$s%')";
}

$sql = "SELECT d.receipt_no, d.created_at, d.first_name, d.last_name, d.mobile, d.amount, d.payment_mode, u.full_name as collector 
        FROM donations d 
        JOIN users u ON d.collected_by = u.id 
        $where 
        ORDER BY d.id DESC";

$res = $conn->query($sql);

// CSV Headers
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="Donation_Report_' . date('Ymd') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Receipt No', 'Date', 'Donor Name', 'Mobile', 'Amount', 'Mode', 'Collector']);

while($row = $res->fetch_assoc()) {
    fputcsv($output, [
        $row['receipt_no'],
        date('Y-m-d H:i', strtotime($row['created_at'])),
        $row['first_name'] . ' ' . $row['last_name'],
        $row['mobile'],
        $row['amount'],
        $row['payment_mode'],
        $row['collector']
    ]);
}
fclose($output);
?>