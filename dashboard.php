<?php
include 'config.php';
require_login();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// 1. Fetch Assigned Organizations
$stmt = $conn->prepare("SELECT o.* FROM organizations o JOIN user_org_mapping m ON o.id = m.org_id WHERE m.user_id = ? AND (o.is_deleted = 0 OR o.is_deleted IS NULL)");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orgs_result = $stmt->get_result();

$org_list = [];
while($row = $orgs_result->fetch_assoc()) {
    $org_list[] = $row;
}
$org_count = count($org_list);

// 2. Handle Selection Logic
$selected_org = null;
if (isset($_GET['org_id'])) {
    $req_id = intval($_GET['org_id']);
    // Verify access
    foreach ($org_list as $o) {
        if ($o['id'] == $req_id) {
            $selected_org = $o;
            $org = $o; // This variable is used by header.php for branding
            break;
        }
    }
    if (!$selected_org) {
        // Invalid ID or no access, redirect to select
        header("Location: dashboard.php");
        exit();
    }
} elseif ($org_count === 1) {
    // Auto-redirect if only one org
    header("Location: dashboard.php?org_id=" . $org_list[0]['id']);
    exit();
}

// 3. Fetch Stats (If Org Selected)
$total_collection = 0;
if ($selected_org) {
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM donations WHERE collected_by = ? AND org_id = ?");
    $stmt->bind_param("ii", $user_id, $selected_org['id']);
    $stmt->execute();
    $total_collection = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .pagination .page-link { cursor: pointer; }
    </style>
</head>
<body class="bg-light pb-5">
    
    <!-- Unified Header (Will adapt based on $org variable) -->
    <?php include 'header.php'; ?>

    <div class="container">
        
        <?php if (!$selected_org): ?>
            <!-- STATE 1: ORGANIZATION SELECTION -->
            <div class="row justify-content-center mt-5">
                <div class="col-md-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-body text-center p-5">
                            <h4 class="mb-4 fw-bold text-secondary">Select Organization</h4>
                            <div class="d-grid gap-3">
                                <?php foreach ($org_list as $row): ?>
                                <a href="dashboard.php?org_id=<?= $row['id'] ?>" class="btn btn-outline-primary btn-lg d-flex align-items-center justify-content-between px-4 py-3">
                                    <span class="d-flex align-items-center">
                                        <?php if($row['logo_path']): ?>
                                            <img src="<?= h($row['logo_path']) ?>" style="height: 30px; width: 30px; object-fit: contain;" class="me-3">
                                        <?php else: ?>
                                            <i class="bi bi-building me-3"></i>
                                        <?php endif; ?>
                                        <?= h($row['name']) ?>
                                    </span>
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                                <?php endforeach; ?>
                                
                                <?php if($org_count == 0): ?>
                                    <div class="alert alert-warning">You are not assigned to any organization. Please contact Admin.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- STATE 2: DASHBOARD FOR SELECTED ORG -->
            
            <!-- 1. HUGE NEW DONATION BUTTON -->
            <div class="row justify-content-center mb-4">
                <div class="col-md-8">
                    <a href="donate.php?org_id=<?= $selected_org['id'] ?>" class="btn btn-primary btn-lg w-100 py-4 shadow-sm border-0 position-relative overflow-hidden" style="background: linear-gradient(135deg, #0d6efd, #0043a8);">
                        <div class="d-flex align-items-center justify-content-center position-relative" style="z-index: 1;">
                            <i class="bi bi-plus-circle-fill display-4 me-3 text-white"></i>
                            <div class="text-start text-white">
                                <div class="h2 mb-0 fw-bold">Collect Donation</div>
                                <small class="opacity-75">Tap to create receipt</small>
                            </div>
                        </div>
                        <!-- Decorative circle -->
                        <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(255,255,255,0.1); border-radius: 50%;"></div>
                    </a>
                </div>
            </div>

            <!-- 2. TOTAL COLLECTION -->
            <div class="row justify-content-center mb-4">
                <div class="col-md-8">
                    <div class="card bg-white shadow-sm border-start border-success border-5">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase mb-1" style="font-size: 0.8rem;">Total Collection (<?= h($selected_org['name']) ?>)</h6>
                                <h2 class="text-success fw-bold mb-0"><?= formatInr($total_collection) ?></h2>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                <i class="bi bi-cash-stack text-success" style="font-size: 1.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. RECENT DONORS (Advanced List) -->
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history text-primary"></i> Recent Donors</h5>
                                <?php if($org_count > 1): ?>
                                    <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">Switch Org</a>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Filters -->
                            <div class="row g-2">
                                <div class="col-8">
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                                        <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Search Name or Mobile..." onkeyup="loadDonors(1)">
                                    </div>
                                </div>
                                <div class="col-4">
                                    <select id="limitSelect" class="form-select" onchange="loadDonors(1)">
                                        <option value="5">5 / page</option>
                                        <option value="10">10</option>
                                        <option value="15">15</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-body p-0">
                            <!-- Dynamic List Container -->
                            <div id="donorListContainer">
                                <div class="text-center py-5 text-muted">
                                    <div class="spinner-border text-primary mb-2" role="status"></div>
                                    <p>Loading records...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Receipt</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body" id="receiptBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printReceipt()">Print / Save</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables for the current Org context
        const currentOrgId = <?= $selected_org ? $selected_org['id'] : 'null' ?>;
        const csrfToken = '<?= $_SESSION['csrf_token'] ?>';

        $(document).ready(function() {
            if(currentOrgId) {
                loadDonors(1);
            }
        });

        function loadDonors(page) {
            if(!currentOrgId) return;

            const search = $('#searchInput').val();
            const limit = $('#limitSelect').val();

            $('#donorListContainer').css('opacity', '0.5'); // Visual feedback

            $.ajax({
                url: 'ajax_handler.php',
                method: 'POST',
                data: {
                    action: 'get_donors',
                    org_id: currentOrgId,
                    search: search,
                    page: page,
                    limit: limit,
                    csrf_token: csrfToken
                },
                success: function(response) {
                    $('#donorListContainer').html(response).css('opacity', '1');
                },
                error: function() {
                    $('#donorListContainer').html('<div class="text-center py-3 text-danger">Error loading data.</div>');
                }
            });
        }

        function fetchReceipt(donationId) {
            $.ajax({
                url: 'ajax_handler.php',
                method: 'POST',
                data: { action: 'get_receipt', donation_id: donationId, csrf_token: csrfToken },
                success: function(resp) {
                    $('#receiptBody').html(resp);
                    new bootstrap.Modal(document.getElementById('receiptModal')).show();
                },
                error: function(xhr) {
                    if(xhr.status === 403) {
                        alert(xhr.responseText);
                        window.location.reload(); 
                    } else {
                        alert('Error loading receipt');
                    }
                }
            });
        }

        function printReceipt() {
            var printContent = document.getElementById('receiptBody').innerHTML;
            var win = window.open('', '', 'width=800,height=600');
            win.document.write('<html><head><title>Receipt</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></head><body>' + printContent + '<script>window.onload = function() { window.print(); setTimeout(function(){window.close();}, 100); }<\/script></body></html>');
            win.document.close();
        }
    </script>
</body>
</html>