<?php
include 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

function uploadFile($file, $dir)
{
    if (!empty($file['name'])) {
        $path = $dir . basename($file['name']);
        move_uploaded_file($file['tmp_name'], $path);
        return $path;
    }
    return null;
}

// --- DATA ---
// Count only non-deleted orgs
$org_count_res = $conn->query("SELECT COUNT(*) as count FROM organizations WHERE is_deleted = 0 OR is_deleted IS NULL");
$org_count = $org_count_res->fetch_assoc()['count'];
$ORG_LIMIT = 2;

// --- ACTIONS ---

// 1. User Approvals & Management
if (isset($_POST['approve_user_id'])) {
    verify_csrf();
    $uid = intval($_POST['approve_user_id']);
    $conn->query("UPDATE users SET is_active = 1 WHERE id = $uid");
    echo "<script>alert('User Approved!'); window.location='admin.php';</script>";
}

if (isset($_POST['toggle_user_id'])) {
    verify_csrf();
    $uid = intval($_POST['toggle_user_id']);
    $new_status = intval($_POST['new_status']);
    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $uid);
    $stmt->execute();
    echo "<script>alert('User Status Updated'); window.location='admin.php';</script>";
}

if (isset($_POST['soft_delete_user_id'])) {
    verify_csrf();
    $uid = intval($_POST['soft_delete_user_id']);
    $conn->query("UPDATE users SET is_deleted = 1 WHERE id = $uid");
    echo "<script>alert('User Soft Deleted'); window.location='admin.php';</script>";
}

// 2. Create Volunteer (Manually)
if (isset($_POST['create_volunteer'])) {
    verify_csrf();
    $name = $_POST['vol_name'];
    $mobile = $_POST['vol_mobile'];
    $address = $_POST['vol_address'];

    // Check if exists
    $check = $conn->query("SELECT id FROM users WHERE mobile = '$mobile'");
    if ($check->num_rows > 0) {
        echo "<script>alert('Error: Mobile number already registered.');</script>";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (full_name, mobile, address, role, is_active, is_deleted) VALUES (?, ?, ?, 'client', 1, 0)");
        $stmt->bind_param("sss", $name, $mobile, $address);
        $stmt->execute();
        echo "<script>alert('Volunteer Created Successfully!'); window.location='admin.php';</script>";
    }
}

// 3. Organization Management
if (isset($_POST['delete_org_id'])) {
    verify_csrf();
    $oid = intval($_POST['delete_org_id']);
    // Soft Delete Organization
    $conn->query("UPDATE organizations SET is_deleted = 1 WHERE id = $oid");
    echo "<script>alert('Organization Deleted'); window.location='admin.php';</script>";
}

if (isset($_POST['create_org']) || isset($_POST['update_org'])) {
    verify_csrf();
    $is_update = isset($_POST['update_org']);
    if (!$is_update && $org_count >= $ORG_LIMIT) {
        echo "<script>alert('Error: Limit reached.'); window.location='admin.php';</script>";
    } else {
        $bank = $_POST['bank'];
        $footer = $_POST['footer'];
        $logo = uploadFile($_FILES['logo'], 'uploads/');
        $qr = uploadFile($_FILES['qr'], 'uploads/');
        $params = [$_POST['org_name'], $_POST['pan'], $_POST['reg80g'], $_POST['upi'], $bank, $_POST['website'], $footer];

        if ($is_update) {
            $types = "sssssss";
            $clauses = "";
            if ($logo) {
                $clauses .= ", logo_path=?";
                $types .= "s";
                $params[] = $logo;
            }
            if ($qr) {
                $clauses .= ", qr_path=?";
                $types .= "s";
                $params[] = $qr;
            }
            $types .= "i";
            $params[] = intval($_POST['org_id']);
            $stmt = $conn->prepare("UPDATE organizations SET name=?, pan_number=?, reg_number_80g=?, upi_id=?, bank_details=?, website=?, footer_text=? $clauses WHERE id=?");
            $stmt->bind_param($types, ...$params);
        } else {
            // New org is active (is_deleted = 0)
            $stmt = $conn->prepare("INSERT INTO organizations (name, pan_number, reg_number_80g, upi_id, bank_details, website, footer_text, logo_path, qr_path, is_deleted) VALUES (?,?,?,?,?,?,?,?,?,0)");
            $stmt->bind_param("sssssssss", $_POST['org_name'], $_POST['pan'], $_POST['reg80g'], $_POST['upi'], $bank, $_POST['website'], $footer, $logo, $qr);
        }
        $stmt->execute();
        echo "<script>alert('Organization Saved'); window.location='admin.php';</script>";
    }
}

// 4. Access Assignment
if (isset($_POST['assign_user'])) {
    verify_csrf();
    $uid = intval($_POST['user_id']);
    $oid = intval($_POST['org_id']);
    $conn->query("INSERT IGNORE INTO user_org_mapping (user_id, org_id) VALUES ($uid, $oid)");
    echo "<script>alert('Access Assigned'); window.location='admin.php';</script>";
}

if (isset($_POST['remove_access_uid']) && isset($_POST['remove_access_oid'])) {
    verify_csrf();
    $uid = intval($_POST['remove_access_uid']);
    $oid = intval($_POST['remove_access_oid']);
    $stmt = $conn->prepare("DELETE FROM user_org_mapping WHERE user_id = ? AND org_id = ?");
    $stmt->bind_param("ii", $uid, $oid);
    $stmt->execute();
    echo "<script>alert('Access Removed'); window.location='admin.php';</script>";
}

// --- FETCH DATA ---
$pending_res = $conn->query("SELECT * FROM users WHERE is_active = 0 AND role != 'admin' AND (is_deleted=0 OR is_deleted IS NULL)");
$pending_count = $pending_res->num_rows;
$total_coll = $conn->query("SELECT SUM(amount) as total FROM donations")->fetch_assoc()['total'] ?? 0;
$vol_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='client' AND (is_deleted=0 OR is_deleted IS NULL)")->fetch_assoc()['count'];

// Fetch Organizations (Exclude Deleted)
$all_orgs_list = $conn->query("SELECT * FROM organizations WHERE is_deleted = 0 OR is_deleted IS NULL");
$org_data_arr = [];
while ($row = $all_orgs_list->fetch_assoc()) $org_data_arr[] = $row;
$org_count_actual = count($org_data_arr);

// Report
$start_date = $_GET['start'] ?? date('Y-m-d');
$end_date = $_GET['end'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';
$filter_collector = isset($_GET['collector_id']) ? intval($_GET['collector_id']) : 0;
$where = "WHERE d.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
if ($search) $where .= " AND (d.first_name LIKE '%$search%' OR u.full_name LIKE '%$search%')";
if ($filter_collector > 0) $where .= " AND d.collected_by = $filter_collector";
$report_res = $conn->query("SELECT d.*, u.full_name as collector FROM donations d JOIN users u ON d.collected_by = u.id $where ORDER BY d.id DESC");
$grand_total = $conn->query("SELECT SUM(d.amount) as gt FROM donations d JOIN users u ON d.collected_by = u.id $where")->fetch_assoc()['gt'] ?? 0;

// Breakdown for Report
$breakdown_res = $conn->query("SELECT d.payment_mode, SUM(d.amount) as total FROM donations d JOIN users u ON d.collected_by = u.id $where GROUP BY d.payment_mode");
$breakdown = ['Cash' => 0, 'UPI' => 0, 'Cheque' => 0, 'BankTransfer' => 0];
while ($row = $breakdown_res->fetch_assoc()) {
    $breakdown[$row['payment_mode']] = $row['total'];
}

$all_collectors = $conn->query("SELECT DISTINCT u.id, u.full_name FROM users u JOIN user_org_mapping m ON u.id = m.user_id WHERE u.role='client'");
$approved_users = $conn->query("SELECT id, full_name, mobile FROM users WHERE role='client' AND is_active=1 AND (is_deleted=0 OR is_deleted IS NULL) ORDER BY full_name ASC");
// Assignment List (Join with deleted check)
$assigned_list = $conn->query("SELECT m.*, u.full_name, u.mobile, o.name as org_name FROM user_org_mapping m JOIN users u ON m.user_id = u.id JOIN organizations o ON m.org_id = o.id WHERE (u.is_deleted=0 OR u.is_deleted IS NULL) AND (o.is_deleted=0 OR o.is_deleted IS NULL) ORDER BY u.full_name");
$all_clients = $conn->query("SELECT * FROM users WHERE role='client' AND (is_deleted=0 OR is_deleted IS NULL) ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Summernote CSS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <style>
        .card-dashboard {
            border-radius: 12px;
            transition: transform 0.2s;
            border: none;
        }

        .card-dashboard:hover {
            transform: translateY(-5px);
        }

        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }

        .nav-tabs .nav-link.active {
            color: #0d6efd;
            border-top: 3px solid #0d6efd;
        }

        .stat-card-icon {
            font-size: 2.5rem;
            opacity: 0.3;
            position: absolute;
            right: 15px;
            bottom: 10px;
        }

        .breakdown-badge {
            font-size: 0.85rem;
            padding: 8px 12px;
            border-radius: 8px;
            margin-right: 5px;
            margin-bottom: 5px;
            display: inline-block;
        }
    </style>
</head>

<body class="bg-light text-secondary">
    <nav class="navbar navbar-dark bg-dark shadow mb-4">
        <div class="container">
            <span class="navbar-brand fw-bold"><i class="bi bi-shield-lock-fill me-2"></i> Admin Console</span>
            <a href="admin_logout.php" class="btn btn-outline-light btn-sm px-3">Logout</a>
        </div>
    </nav>

    <div class="container pb-5">
        <ul class="nav nav-tabs mb-4 border-bottom-0" id="adminTabs">
            <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#dashboard" id="tab-dashboard"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#reports" id="tab-reports"><i class="bi bi-bar-chart-fill me-1"></i> Reports</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#users" id="tab-users"><i class="bi bi-people-fill me-1"></i> Manage Access</a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#approvals" id="tab-approvals"><i class="bi bi-check-circle-fill me-1"></i> Approvals <?php if ($pending_count > 0): ?><span class="badge bg-danger ms-1 rounded-pill"><?= $pending_count ?></span><?php endif; ?></a></li>
            <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#orgs" id="tab-orgs"><i class="bi bi-building me-1"></i> Organizations</a></li>
        </ul>

        <div class="tab-content bg-white p-4 rounded shadow-sm" style="min-height: 600px;">

            <!-- 1. DASHBOARD TAB -->
            <div class="tab-pane fade show active" id="dashboard">
                <h4 class="mb-4 text-dark fw-bold">Overview</h4>
                <div class="row g-4">
                    <!-- Pending Approvals -->
                    <div class="col-md-3">
                        <div class="card card-dashboard bg-warning text-dark h-100 shadow-sm" onclick="switchTab('#approvals')" style="cursor:pointer;">
                            <div class="card-body position-relative">
                                <h6 class="card-title text-uppercase small fw-bold opacity-75">Pending Approvals</h6>
                                <h2 class="display-5 fw-bold mb-0"><?= $pending_count ?></h2>
                                <i class="bi bi-hourglass-split stat-card-icon"></i>
                            </div>
                        </div>
                    </div>
                    <!-- Total Collection -->
                    <div class="col-md-3">
                        <div class="card card-dashboard bg-success text-white h-100 shadow-sm" onclick="switchTab('#reports')" style="cursor:pointer;">
                            <div class="card-body position-relative">
                                <h6 class="card-title text-uppercase small fw-bold opacity-75">Total Collection</h6>
                                <h2 class="display-6 fw-bold mb-0"><?= formatInr($total_coll) ?></h2>
                                <i class="bi bi-currency-rupee stat-card-icon"></i>
                            </div>
                        </div>
                    </div>
                    <!-- Organizations -->
                    <div class="col-md-3">
                        <div class="card card-dashboard bg-primary text-white h-100 shadow-sm" onclick="switchTab('#orgs')" style="cursor:pointer;">
                            <div class="card-body position-relative">
                                <h6 class="card-title text-uppercase small fw-bold opacity-75">Organizations</h6>
                                <h2 class="display-5 fw-bold mb-0"><?= $org_count_actual ?></h2>
                                <i class="bi bi-building stat-card-icon"></i>
                            </div>
                        </div>
                    </div>
                    <!-- Volunteers -->
                    <div class="col-md-3">
                        <div class="card card-dashboard bg-info text-white h-100 shadow-sm" onclick="switchTab('#users')" style="cursor:pointer;">
                            <div class="card-body position-relative">
                                <h6 class="card-title text-uppercase small fw-bold opacity-75">Active Volunteers</h6>
                                <h2 class="display-5 fw-bold mb-0"><?= $vol_count ?></h2>
                                <i class="bi bi-people stat-card-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md p-10 mt-4 bg-light border rounded text-center">
                    <h3>
                        जय श्री राम 🙏<br>
                        ॐ रामायणाय नमः ।।<br>
                        <br>
                        <strong>मंगल भवन अमंगल हारी,<br>
                            द्रवउ सो दसरथ अजिर बिहारी ।।</strong><br>
                        <br>
                        स्वागत है Admin!<br>
                        श्री राम की कृपा से आपका दिन शुभ, शांत और सफल हो।
                    </h3>

                </div>
            </div>

            <!-- 2. REPORTS TAB (Split Grand Total) -->
            <div class="tab-pane fade" id="reports">
                <div class="card bg-light border-0 mb-4">
                    <div class="card-body">
                        <form class="row g-3 align-items-end">
                            <div class="col-md-2"><label class="form-label small text-muted fw-bold">Start Date</label><input type="date" name="start" id="startDate" value="<?= $start_date ?>" class="form-control" onchange="validateDates()"></div>
                            <div class="col-md-2"><label class="form-label small text-muted fw-bold">End Date</label><input type="date" name="end" id="endDate" value="<?= $end_date ?>" class="form-control" onchange="validateDates()"></div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted fw-bold">Collector</label>
                                <select id="reportCollector" name="collector_id" class="form-select">
                                    <option value="0">All Collectors</option>
                                    <?php $all_collectors->data_seek(0);
                                    while ($c = $all_collectors->fetch_assoc()): ?>
                                        <option value="<?= $c['id'] ?>" <?= ($filter_collector == $c['id']) ? 'selected' : '' ?>><?= h($c['full_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3 d-grid"><button class="btn btn-primary fw-bold"><i class="bi bi-funnel"></i> Filter Report</button></div>
                            <div class="col-md-2 d-grid"><button class="btn btn-success" onclick="exportReport()"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</button></div>
                        </form>
                    </div>
                </div>

                <!-- Financial Summary Bar -->
                <div class="row mb-4 g-3">
                    <div class="col-md-4">
                        <div class="card bg-success text-white h-100">
                            <div class="card-body text-center">
                                <small class="text-uppercase opacity-75 fw-bold">Grand Total</small>
                                <h2 class="fw-bold mb-0"><?= formatInr($grand_total) ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card h-100 border-0 bg-light">
                            <div class="card-body d-flex flex-wrap align-items-center justify-content-around">
                                <div class="text-center px-2">
                                    <small class="text-muted d-block">Cash</small>
                                    <span class="fw-bold text-dark fs-5"><?= formatInr($breakdown['Cash']) ?></span>
                                </div>
                                <div class="vr"></div>
                                <div class="text-center px-2">
                                    <small class="text-muted d-block">UPI / QR</small>
                                    <span class="fw-bold text-primary fs-5"><?= formatInr($breakdown['UPI']) ?></span>
                                </div>
                                <div class="vr"></div>
                                <div class="text-center px-2">
                                    <small class="text-muted d-block">Cheque</small>
                                    <span class="fw-bold text-warning fs-5"><?= formatInr($breakdown['Cheque']) ?></span>
                                </div>
                                <div class="vr"></div>
                                <div class="text-center px-2">
                                    <small class="text-muted d-block">Bank</small>
                                    <span class="fw-bold text-info fs-5"><?= formatInr($breakdown['BankTransfer']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label small text-muted fw-bold">Search Donor</label>
                        <input type="text" id="reportSearch" class="form-control form-control-sm" placeholder="Name or Mobile..." onkeyup="loadReports(1)">
                    </div>
                    <div class="col-md-2 ms-auto">
                        <label class="form-label small text-muted fw-bold">Rows</label>
                        <select id="reportLimit" class="form-select form-select-sm" onchange="loadReports(1)">
                            <option value="5">5</option>
                            <option value="10" selected>10</option>
                            <option value="15">15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive border rounded">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Rec#</th>
                                <th>Date</th>
                                <th>Donor</th>
                                <th>Collector</th>
                                <th>Mode</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($r = $report_res->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border"><?= h($r['receipt_no']) ?></span></td>
                                    <td><?= date('d M, Y', strtotime($r['created_at'])) ?></td>
                                    <td><?= h($r['first_name']) ?></td>
                                    <td><?= h($r['collector']) ?></td>
                                    <td><span class="badge bg-secondary"><?= h($r['payment_mode']) ?></span></td>
                                    <td class="text-end fw-bold text-success"><?= formatInr($r['amount']) ?></td>
                                    <td class="text-center"><button class="btn btn-sm btn-outline-primary py-0" onclick="fetchReceipt(<?= $r['id'] ?>)"><i class="bi bi-eye"></i> View</button></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3. MANAGE ACCESS / VOLUNTEERS -->
            <div class="tab-pane fade" id="users">

                <!-- Top Action Bar -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="text-dark fw-bold mb-0">Volunteer Management</h4>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addVolModal">
                        <i class="bi bi-person-plus-fill"></i> Create New Volunteer
                    </button>
                </div>

                <div class="row">
                    <!-- Assign Access -->
                    <div class="col-md-5">
                        <div class="card shadow-sm h-100 border-0">
                            <div class="card-header bg-white fw-bold">Assign Organization</div>
                            <div class="card-body">
                                <form method="POST">
                                    <?= csrf_token_field() ?>
                                    <input type="hidden" name="assign_user" value="1">
                                    <div class="mb-3">
                                        <label class="form-label small text-muted">1. Search Volunteer</label>
                                        <input type="text" id="uSearch" class="form-control form-control-sm mb-2" placeholder="Type name..." onkeyup="filterUsers()">
                                        <select name="user_id" id="uSelect" class="form-select form-select-sm" size="4">
                                            <?php while ($u = $approved_users->fetch_assoc()): ?>
                                                <option value="<?= $u['id'] ?>"><?= h($u['full_name']) ?> (<?= h($u['mobile']) ?>)</option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small text-muted">2. Select Organization</label>
                                        <select name="org_id" class="form-select">
                                            <option value="">Choose...</option>
                                            <?php foreach ($org_data_arr as $o) echo "<option value='{$o['id']}'>{$o['name']}</option>"; ?>
                                        </select>
                                    </div>
                                    <button class="btn btn-primary w-100">Grant Access</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Active Assignments -->
                    <div class="col-md-7">
                        <div class="card shadow-sm h-100 border-0">
                            <div class="card-header bg-white fw-bold">Current Access Assignments</div>
                            <div class="card-body p-0">
                                <div class="table-responsive" style="max-height:350px;">
                                    <table class="table table-striped mb-0 align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Volunteer</th>
                                                <th>Organization</th>
                                                <th class="text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($r = $assigned_list->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?= h($r['full_name']) ?></td>
                                                    <td><span class="badge bg-light text-dark border"><?= h($r['org_name']) ?></span></td>
                                                    <td class="text-end">
                                                        <form method="POST" onsubmit="return confirm('Revoke access?');">
                                                            <?= csrf_token_field() ?>
                                                            <input type="hidden" name="remove_access_uid" value="<?= $r['user_id'] ?>">
                                                            <input type="hidden" name="remove_access_oid" value="<?= $r['org_id'] ?>">
                                                            <button class="btn btn-sm btn-outline-danger border-0" title="Unassign"><i class="bi bi-x-circle-fill"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <h5 class="fw-bold mb-3">All Volunteers Status</h5>
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Mobile</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($c = $all_clients->fetch_assoc()):
                                        $ia = $c['is_active'];
                                        $statusBadge = $ia ? '<span class="badge bg-success-subtle text-success border border-success">Active</span>' : '<span class="badge bg-danger-subtle text-danger border border-danger">Disabled</span>';
                                        $toggleBtn = $ia ? '<button class="btn btn-sm btn-warning" title="Disable Access"><i class="bi bi-pause-fill"></i></button>' : '<button class="btn btn-sm btn-success" title="Enable Access"><i class="bi bi-play-fill"></i></button>';
                                        $ns = $ia ? 0 : 1;
                                    ?>
                                        <tr>
                                            <td class="fw-bold"><?= h($c['full_name']) ?></td>
                                            <td><?= h($c['mobile']) ?></td>
                                            <td><?= $statusBadge ?></td>
                                            <td class="text-end">
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Change status for <?= h($c['full_name']) ?>?')">
                                                    <?= csrf_token_field() ?>
                                                    <input type="hidden" name="toggle_user_id" value="<?= $c['id'] ?>">
                                                    <input type="hidden" name="new_status" value="<?= $ns ?>">
                                                    <?= $toggleBtn ?>
                                                </form>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently hide <?= h($c['full_name']) ?> from lists?')">
                                                    <?= csrf_token_field() ?>
                                                    <input type="hidden" name="soft_delete_user_id" value="<?= $c['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash-fill"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. APPROVALS -->
            <div class="tab-pane fade" id="approvals">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-warning bg-opacity-25 fw-bold text-warning-emphasis">Pending Approvals</div>
                    <div class="card-body p-0">
                        <?php if ($pending_res->num_rows == 0): ?>
                            <div class="p-5 text-center text-muted">
                                <i class="bi bi-check-circle display-4 mb-3 d-block opacity-25"></i>
                                No pending registration requests.
                            </div>
                        <?php else: ?>
                            <table class="table mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Mobile</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($u = $pending_res->fetch_assoc()): ?>
                                        <tr>
                                            <td class="fw-bold"><?= h($u['full_name']) ?></td>
                                            <td><?= h($u['mobile']) ?></td>
                                            <td class="text-end">
                                                <form method="POST">
                                                    <?= csrf_token_field() ?>
                                                    <input type="hidden" name="approve_user_id" value="<?= $u['id'] ?>">
                                                    <button class="btn btn-success btn-sm fw-bold px-3">Approve <i class="bi bi-check-lg"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 5. ORGANIZATIONS -->
            <div class="tab-pane fade" id="orgs">
                <div class="row">
                    <!-- Org List -->
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-header bg-white fw-bold">Active Organizations</div>
                            <div class="card-body p-0">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>PAN</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($org_data_arr as $o):
                                            $org_json = htmlspecialchars(json_encode($o), ENT_QUOTES, 'UTF-8');
                                        ?>
                                            <tr>
                                                <td class="fw-bold"><?= h($o['name']) ?></td>
                                                <td><small class="text-muted"><?= h($o['pan_number']) ?></small></td>
                                                <td class="text-end">
                                                    <button class="btn btn-outline-primary btn-sm me-1" onclick="editOrg(<?= $org_json ?>)"><i class="bi bi-pencil-fill"></i></button>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete Organization? This will hide it from users.');">
                                                        <?= csrf_token_field() ?>
                                                        <input type="hidden" name="delete_org_id" value="<?= $o['id'] ?>">
                                                        <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash-fill"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Add Org Form -->
                    <div class="col-md-6">
                        <?php if ($org_count_actual < $ORG_LIMIT): ?>
                            <div class="card shadow-sm border-0 bg-light">
                                <div class="card-body">
                                    <h5 class="fw-bold mb-3"><i class="bi bi-plus-circle"></i> Add Organization</h5>
                                    <form method="POST" enctype="multipart/form-data">
                                        <?= csrf_token_field() ?>
                                        <input type="hidden" name="create_org" value="1">
                                        <div class="mb-2"><input type="text" name="org_name" class="form-control" placeholder="Organization Name" required></div>
                                        <div class="mb-2"><input type="text" name="pan" class="form-control" placeholder="PAN Number"></div>
                                        <div class="mb-2"><input type="text" name="reg80g" class="form-control" placeholder="80G Reg No"></div>
                                        <div class="mb-2"><input type="text" name="upi" class="form-control" placeholder="UPI ID (for QR)"></div>
                                        <div class="mb-2"><input type="text" name="website" class="form-control" placeholder="Website URL"></div>

                                        <div class="mb-2">
                                            <label class="small text-muted">Bank Details (Rich Text)</label>
                                            <textarea name="bank" class="summernote"></textarea>
                                        </div>
                                        <div class="mb-2">
                                            <label class="small text-muted">Footer (Rich Text)</label>
                                            <textarea name="footer" class="summernote"></textarea>
                                        </div>

                                        <div class="mb-2"><label class="small text-muted">Logo</label><input type="file" name="logo" class="form-control form-control-sm"></div>
                                        <div class="mb-3"><label class="small text-muted">QR Image</label><input type="file" name="qr" class="form-control form-control-sm"></div>
                                        <button class="btn btn-primary w-100">Save Organization</button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Organization limit (<?= $ORG_LIMIT ?>) reached.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Volunteer Modal -->
    <div class="modal fade" id="addVolModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Create Volunteer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <?= csrf_token_field() ?>
                        <input type="hidden" name="create_volunteer" value="1">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="vol_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mobile Number</label>
                            <input type="number" name="vol_mobile" class="form-control" required placeholder="10 digit mobile">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address / Area</label>
                            <input type="text" name="vol_address" class="form-control">
                        </div>
                        <div class="alert alert-light border small text-muted">
                            <i class="bi bi-info-circle"></i> User will be active immediately. They can login using OTP sent to this mobile number.
                        </div>
                        <button class="btn btn-success w-100">Create & Activate</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Org Modal -->
    <div class="modal fade" id="editOrgModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5>Edit Organization</h5><button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrf_token_field() ?>
                        <input type="hidden" name="update_org" value="1">
                        <input type="hidden" name="org_id" id="edit_id">
                        <div class="row g-3">
                            <div class="col-md-6"><label>Name</label><input type="text" name="org_name" id="edit_name" class="form-control" required></div>
                            <div class="col-md-3"><label>PAN</label><input type="text" name="pan" id="edit_pan" class="form-control"></div>
                            <div class="col-md-3"><label>80G</label><input type="text" name="reg80g" id="edit_80g" class="form-control"></div>
                            <div class="col-md-6"><label>UPI</label><input type="text" name="upi" id="edit_upi" class="form-control"></div>
                            <div class="col-md-6"><label>Website</label><input type="text" name="website" id="edit_web" class="form-control"></div>

                            <div class="col-12"><label>Bank</label><textarea name="bank" id="edit_bank" class="summernote"></textarea></div>
                            <div class="col-12"><label>Footer</label><textarea name="footer" id="edit_footer" class="summernote"></textarea></div>

                            <div class="col-md-6"><label>New Logo</label><input type="file" name="logo" class="form-control"></div>
                            <div class="col-md-6"><label>New QR</label><input type="file" name="qr" class="form-control"></div>
                            <div class="col-12 text-end"><button class="btn btn-primary">Update Changes</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Shared Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body" id="receiptBody"></div>
                <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-primary" onclick="printReceipt()">Print</button></div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>

    <script>
        const csrfToken = '<?= $_SESSION['csrf_token'] ?>';

        $(document).ready(function() {
            $('.summernote').summernote({
                height: 100,
                toolbar: [
                    ['style', ['bold', 'italic', 'underline']],
                    ['para', ['ul', 'ol']]
                ]
            });
            validateDates();

            // RE-ACTIVATE TAB ON LOAD
            // This handles the "Stay on same tab" requirement
            var activeTab = localStorage.getItem('activeTab');
            if (activeTab) {
                var tabTrigger = document.querySelector('a[href="' + activeTab + '"]');
                if (tabTrigger) {
                    new bootstrap.Tab(tabTrigger).show();
                }
            }
        });

        // Save tab state on click
        document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function(event) {
                localStorage.setItem('activeTab', event.target.getAttribute('href'));
            });
        });

        // Force tab switch helper
        function switchTab(tabId) {
            var tabEl = document.querySelector('a[href="' + tabId + '"]');
            if (tabEl) {
                new bootstrap.Tab(tabEl).show();
                localStorage.setItem('activeTab', tabId);
                window.scrollTo(0, 0);
            }
        }

        // ... Existing Utility Functions (Date, Receipt, Org Edit) ...
        function validateDates() {
            const s = document.getElementById('startDate'),
                e = document.getElementById('endDate');
            const off = new Date().getTimezoneOffset() * 60000;
            const today = new Date(Date.now() - off).toISOString().split('T')[0];
            s.max = today;
            e.max = today;
            if (s.value > today) s.value = today;
            if (e.value > today) e.value = today;
            if (s.value && e.value && e.value < s.value) e.value = s.value;
            if (s.value) e.min = s.value;
        }

        // --- AJAX REPORT LOGIC ---
        let searchTimer;

        function loadReports(page) {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                const start = $('#startDate').val();
                const end = $('#endDate').val();
                const collector = $('#reportCollector').val();
                const search = $('#reportSearch').val();
                const limit = $('#reportLimit').val();

                $('#reportTableContainer').css('opacity', 0.5);

                $.ajax({
                    url: 'ajax_handler.php',
                    method: 'POST',
                    data: {
                        action: 'get_admin_reports',
                        start: start,
                        end: end,
                        collector: collector,
                        search: search,
                        page: page,
                        limit: limit,
                        csrf_token: csrfToken
                    },
                    dataType: 'json',
                    success: function(res) {
                        $('#reportStatsContainer').html(res.stats);
                        $('#reportTableContainer').html(res.table).css('opacity', 1);
                    }
                });
            }, 300); // Debounce
        }

        function exportReport() {
            const start = $('#startDate').val();
            const end = $('#endDate').val();
            const collector = $('#reportCollector').val();
            const search = $('#reportSearch').val();
            const url = `export_report.php?start=${start}&end=${end}&collector=${collector}&search=${search}`;
            window.open(url, '_blank');
        }

        function fetchReceipt(id) {
            fetch('ajax_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=get_receipt&donation_id=' + id + '&csrf_token=<?= $_SESSION['csrf_token'] ?>'
            }).then(r => r.text()).then(h => {
                document.getElementById('receiptBody').innerHTML = h;
                new bootstrap.Modal(document.getElementById('receiptModal')).show();
            });
        }

        function printReceipt() {
            var c = document.getElementById('receiptBody').innerHTML;
            var w = window.open('', '', 'width=800,height=600');
            w.document.write('<html><head><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></head><body>' + c + '<script>window.onload=function(){window.print();setTimeout(function(){window.close();},100);}<\/script></body></html>');
            w.document.close();
        }

        function filterUsers() {
            var v = document.getElementById('uSearch').value.toUpperCase();
            var ops = document.getElementById('uSelect').options;
            for (var i = 0; i < ops.length; i++) ops[i].style.display = ops[i].text.toUpperCase().indexOf(v) > -1 ? "" : "none";
        }

        function editOrg(d) {
            document.getElementById('edit_id').value = d.id;
            document.getElementById('edit_name').value = d.name;
            document.getElementById('edit_pan').value = d.pan_number;
            document.getElementById('edit_80g').value = d.reg_number_80g;
            document.getElementById('edit_upi').value = d.upi_id;
            document.getElementById('edit_web').value = d.website;
            $('#edit_bank').summernote('code', d.bank_details);
            $('#edit_footer').summernote('code', d.footer_text);
            new bootstrap.Modal(document.getElementById('editOrgModal')).show();
        }
    </script>
</body>

</html>