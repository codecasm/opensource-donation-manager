<?php
include 'config.php';
require_login();

// Function to Generate Receipt HTML (Same as before)
function generateReceiptHTML($res) {
    $wa_text = urlencode("Donation Receipt\nOrg: " . $res['org_name'] . "\nReceipt No: " . $res['receipt_no'] . "\nAmount: ₹" . $res['amount'] . "\n\nThank you " . $res['first_name'] . " for your support!");
    $wa_link = "https://wa.me/?text=$wa_text";
    
    ob_start();
    ?>
    <div style="border: 4px double #333; padding: 20px; font-family: 'Arial', sans-serif; position: relative; color: #333;">
        <div class="text-center mb-3">
            <?php if(!empty($res['logo_path']) && file_exists($res['logo_path'])): ?>
                <img src="<?= h($res['logo_path']) ?>" style="height: 60px; display:block; margin: 0 auto 10px auto;">
            <?php endif; ?>
            <h4 class="mt-0 mb-0 text-uppercase fw-bold"><?= h($res['org_name']) ?></h4>
            <?php if(!empty($res['website'])): ?>
                <small class="text-muted d-block"><?= h($res['website']) ?></small>
            <?php endif; ?>
            
            <?php 
            $meta = [];
            if(!empty($res['reg_number_80g'])) $meta[] = "Reg: " . h($res['reg_number_80g']);
            if(!empty($res['pan_number'])) $meta[] = "PAN: " . h($res['pan_number']);
            if(!empty($meta)): 
            ?>
            <div style="font-size: 11px; margin-top: 5px;">
                <?= implode(" | ", $meta) ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div style="border-top: 1px dashed #ccc; border-bottom: 1px dashed #ccc; padding: 5px 0; margin-bottom: 15px; display: flex; justify-content: space-between;">
            <small>Rec #: <strong><?= h($res['receipt_no']) ?></strong></small>
            <small><strong>Date:</strong> <?= date('d-M-Y', strtotime($res['created_at'])) ?></small>
        </div>

        <p class="mb-1">Received with thanks from:</p>
        <p class="mb-2 ms-3">
            <strong><?= h($res['first_name'] . ' ' . $res['last_name']) ?></strong><br>
            <small><strong>Mobile No.:</strong> <?= h($res['mobile']) ?></small><br>
            <?php if(!empty($res['address'])): ?>
                <small><strong>Address:</strong> <?= h($res['address']) ?></small>
            <?php endif; ?>
        </p>
        
        <div class="alert alert-light border text-center py-3 my-3" style="background-color: #f8f9fa;">
            <h2 class="mb-0" style="font-weight: 900; font-size: 2.5rem;"><?= formatInr($res['amount']) ?></h2>
            <small class="text-muted text-uppercase" style="font-size: 0.7rem; font-weight: bold;"><?= numberToWords($res['amount']) ?></small>
        </div>

        <div class="mb-3 small">
            <strong>Mode:</strong> <?= h($res['payment_mode']) ?>
            <?php if($res['payment_mode'] == 'Cheque'): ?>
                <br>Bank: <?= h($res['bank_name']) ?> - <?= h($res['branch_name']) ?>
                <br>Chq: <?= h($res['cheque_no']) ?> (<?= $res['cheque_date'] ?>)
            <?php elseif(in_array($res['payment_mode'], ['UPI','BankTransfer']) && !empty($res['utr_number'])): ?>
                <br>Ref/UTR: <?= h($res['utr_number']) ?>
            <?php endif; ?>
        </div>

        <div class="mt-5 pt-2 text-end position-relative">
            <div style="font-family: 'Brush Script MT', cursive; font-size: 22px; color: #0044cc; position: absolute; bottom: 15px; right: 10px;">
                <?= h($res['collector_name']) ?>
            </div>
            <br>
            <small style="border-top: 1px solid #000; display: inline-block; width: 150px; margin-top: 20px;">Collector/Fundraiser</small>
        </div>
        
        <?php if(!empty($res['footer_text'])): ?>
        <div class="text-center mt-4 pt-2 small text-muted">
            <hr>
            <?= clean_html($res['footer_text']) ?>
        </div>
        <?php endif; ?>

        <div class="text-center mt-3 d-print-none">
            <a href="<?= $wa_link ?>" target="_blank" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i> Share on WhatsApp</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

if(isset($_POST['action'])) {
    verify_csrf();

    // --- ACTION 1: GET DONOR LIST (New) ---
    if($_POST['action'] == 'get_donors') {
        $org_id = intval($_POST['org_id']);
        $user_id = $_SESSION['user_id'];
        $search = isset($_POST['search']) ? trim($_POST['search']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 5;
        $offset = ($page - 1) * $limit;

        // Base Query Condition
        $where = "WHERE collected_by = ? AND org_id = ?";
        $params = ["ii", $user_id, $org_id];

        // Add Search
        if(!empty($search)) {
            $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR mobile LIKE ?)";
            $search_term = "%$search%";
            $params[0] .= "sss";
            $params[] = $search_term; $params[] = $search_term; $params[] = $search_term;
        }

        // 1. Get Total Count for Pagination
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM donations $where");
        $bind_names = $params;
        unset($bind_names[0]); // Remove types string for bind_param
        // We need to bind dynamically
        $count_stmt->bind_param($params[0], ...$bind_names);
        $count_stmt->execute();
        $total_records = $count_stmt->get_result()->fetch_assoc()['count'];
        $total_pages = ceil($total_records / $limit);

        // 2. Get Data
        $sql = "SELECT * FROM donations $where ORDER BY id DESC LIMIT ? OFFSET ?";
        $params[0] .= "ii";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $conn->prepare($sql);
        $bind_names = $params; unset($bind_names[0]);
        $stmt->bind_param($params[0], ...$bind_names);
        $stmt->execute();
        $result = $stmt->get_result();

        // 3. Render HTML
        if($result->num_rows > 0) {
            echo '<ul class="list-group list-group-flush">';
            while($r = $result->fetch_assoc()) {
                ?>
                <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                    <div>
                        <strong class="text-dark"><?= h($r['first_name']) . ' ' . h($r['last_name']) ?></strong> 
                        <span class="badge bg-light text-secondary border ms-1 fw-normal"><?= h($r['payment_mode']) ?></span>
                        <br>
                        <small class="text-muted" style="font-size: 0.75rem;">
                            <?= date('d M, h:i A', strtotime($r['created_at'])) ?> | <?= h($r['mobile']) ?>
                        </small>
                    </div>
                    <div class="text-end">
                        <span class="text-success fw-bold d-block"><?= formatInr($r['amount']) ?></span>
                        <button class="btn btn-sm btn-outline-primary mt-1 py-0 rounded-pill" style="font-size: 10px;" onclick="fetchReceipt(<?= $r['id'] ?>)">
                            <i class="bi bi-receipt"></i> Receipt
                        </button>
                    </div>
                </li>
                <?php
            }
            echo '</ul>';

            // Render Pagination Controls
            if($total_pages > 1) {
                echo '<div class="d-flex justify-content-between align-items-center p-3 border-top bg-light">';
                
                // Previous Button
                $prev_disabled = ($page <= 1) ? 'disabled' : '';
                $prev_page = $page - 1;
                echo "<button class='btn btn-sm btn-outline-secondary' $prev_disabled onclick='loadDonors($prev_page)'>&laquo; Prev</button>";
                
                echo "<small class='text-muted'>Page $page of $total_pages</small>";
                
                // Next Button
                $next_disabled = ($page >= $total_pages) ? 'disabled' : '';
                $next_page = $page + 1;
                echo "<button class='btn btn-sm btn-outline-secondary' $next_disabled onclick='loadDonors($next_page)'>Next &raquo;</button>";
                
                echo '</div>';
            }

        } else {
            echo '<div class="text-center py-4 text-muted">No records found.</div>';
        }
    }

    // --- ACTION 2: SAVE DONATION ---
    if($_POST['action'] == 'save_donation') {
        $org_id = intval($_POST['org_id']);
        $user_id = $_SESSION['user_id'];
        $fname = $_POST['fname']; $lname = $_POST['lname']; $mobile = $_POST['mobile']; $addr = $_POST['address'];
        $amount = floatval($_POST['amount']); $mode = $_POST['mode'];
        $receipt_no = 'REC-' . strtoupper(substr(md5(uniqid()), 0, 6));
        
        $bank = $_POST['bank_name'] ?? null; $branch = $_POST['branch_name'] ?? null;
        $chq_no = $_POST['cheque_no'] ?? null; $chq_date = !empty($_POST['cheque_date']) ? $_POST['cheque_date'] : null;
        $utr = (!empty($_POST['utr_upi'])) ? $_POST['utr_upi'] : ((!empty($_POST['utr_bank'])) ? $_POST['utr_bank'] : null);

        $stmt = $conn->prepare("INSERT INTO donations (receipt_no, org_id, collected_by, first_name, last_name, mobile, address, amount, payment_mode, bank_name, branch_name, cheque_no, cheque_date, utr_number, payment_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Success')");
        $stmt->bind_param("siissssdssssss", $receipt_no, $org_id, $user_id, $fname, $lname, $mobile, $addr, $amount, $mode, $bank, $branch, $chq_no, $chq_date, $utr);
        
        if($stmt->execute()) {
            $don_id = $stmt->insert_id;
            $res = $conn->query("SELECT d.*, o.name as org_name, o.logo_path, o.pan_number, o.reg_number_80g, o.footer_text, o.website, u.full_name as collector_name FROM donations d JOIN organizations o ON d.org_id = o.id JOIN users u ON d.collected_by = u.id WHERE d.id = $don_id")->fetch_assoc();
            echo generateReceiptHTML($res);
        } else {
            echo "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
        }
    }

    // --- ACTION 3: VIEW RECEIPT ---
    if($_POST['action'] == 'get_receipt') {
        $don_id = intval($_POST['donation_id']);
        $res = $conn->query("SELECT d.*, o.name as org_name, o.logo_path, o.pan_number, o.reg_number_80g, o.footer_text, o.website, u.full_name as collector_name FROM donations d JOIN organizations o ON d.org_id = o.id JOIN users u ON d.collected_by = u.id WHERE d.id = $don_id");
        
        if($res->num_rows > 0) {
            echo generateReceiptHTML($res->fetch_assoc());
        } else {
            echo "Receipt not found.";
        }
    }
}
?>