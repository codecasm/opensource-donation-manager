<?php
include 'config.php';
require_login();

if (!isset($_GET['org_id'])) header("Location: dashboard.php");
$org_id = intval($_GET['org_id']);
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM organizations WHERE id=?");
$stmt->bind_param("i", $org_id);
$stmt->execute();
$org_res = $stmt->get_result();

if ($org_res->num_rows === 0) die("<div class='alert alert-danger'>Org not found.</div>");
$org = $org_res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($org['name']) ?> Donation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .qr-container { background: #fff; padding: 15px; border: 2px dashed #ccc; text-align: center; border-radius: 10px; display: none;}
        .bank-container { background: #e9ecef; padding: 15px; border-radius: 8px; border-left: 5px solid #0d6efd; display: none; }
    </style>
</head>
<body class="bg-light">

<?php include 'header.php'; ?>

<div class="container">
    <div class="card shadow border-0 mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span>New Donation</span>
            <small class="text-light bg-white px-2 rounded text-primary d-md-none signature-font"><?= h($_SESSION['user_name']) ?></small>
        </div>
        <div class="card-body">
            <form id="donationForm">
                <?= csrf_token_field() ?>
                <input type="hidden" name="org_id" value="<?= $org_id ?>">
                <input type="hidden" name="action" value="save_donation">

                <div class="row g-2 mb-3">
                    <div class="col-6"><input type="text" name="fname" class="form-control" placeholder="First Name" required pattern=".{2,}" title="Minimum 2 characters"></div>
                    <div class="col-6"><input type="text" name="lname" class="form-control" placeholder="Last Name" required pattern=".{2,}" title="Minimum 2 characters"></div>
                </div>
                <div class="mb-3">
                    <input type="tel" name="mobile" class="form-control" placeholder="Donor Mobile (10 Digits)" required pattern="[0-9]{10}" title="Please enter a valid 10-digit mobile number" maxlength="10">
                </div>
                <div class="mb-3"><textarea name="address" class="form-control" placeholder="Short Address / City" rows="1"></textarea></div>

                <label class="form-label fw-bold">Payment Method</label>
                <div class="btn-group w-100 mb-3" role="group">
                    <input type="radio" class="btn-check" name="mode" id="cash" value="Cash" checked onchange="toggleMode()">
                    <label class="btn btn-outline-dark" for="cash">Cash</label>
                    <input type="radio" class="btn-check" name="mode" id="upi" value="UPI" onchange="toggleMode()">
                    <label class="btn btn-outline-dark" for="upi">UPI/QR</label>
                    <input type="radio" class="btn-check" name="mode" id="bank" value="BankTransfer" onchange="toggleMode()">
                    <label class="btn btn-outline-dark" for="bank">Bank</label>
                    <input type="radio" class="btn-check" name="mode" id="chq" value="Cheque" onchange="toggleMode()">
                    <label class="btn btn-outline-dark" for="chq">Cheque</label>
                </div>

                <div id="mode_upi" class="qr-container mb-3">
                    <?php if(!empty($org['qr_path'])): ?>
                        <img src="<?= h($org['qr_path']) ?>" class="img-fluid" style="max-height: 150px;">
                        <p class="small text-muted mt-2">Scan Org QR Code</p>
                    <?php else: ?>
                        <p class="text-danger">No QR Image uploaded by Admin</p>
                    <?php endif; ?>
                    <input type="text" name="utr_upi" class="form-control mt-2" placeholder="Enter UTR / Ref No (Optional)">
                </div>

                <div id="mode_bank" class="bank-container mb-3">
                    <h6 class="text-primary"><i class="bi bi-bank"></i> Bank Details</h6>
                    <!-- USED CLEAN_HTML HERE FOR RICH TEXT -->
                    <div class="mb-2 small">
                        <?= !empty($org['bank_details']) ? clean_html($org['bank_details']) : 'No Bank Details Provided' ?>
                    </div>
                    <hr>
                    <label class="small text-muted">Transaction Reference</label>
                    <input type="text" name="utr_bank" class="form-control" placeholder="Enter Ref / Transaction ID">
                </div>

                <div id="mode_cheque" class="card bg-light p-2 mb-3" style="display: none;">
                    <h6 class="text-primary ps-1 pt-1">Cheque Details</h6>
                    <div class="mb-2"><input type="text" name="bank_name" class="form-control chq-field" placeholder="Bank Name"></div>
                    <div class="mb-2"><input type="text" name="branch_name" class="form-control chq-field" placeholder="Branch Name"></div>
                    <div class="row g-2">
                        <div class="col-6"><input type="text" name="cheque_no" class="form-control chq-field" placeholder="Chq No"></div>
                        <div class="col-6"><input type="date" name="cheque_date" class="form-control chq-field"></div>
                    </div>
                </div>

                <div class="input-group input-group-lg mb-3">
                    <span class="input-group-text">₹</span>
                    <input type="number" name="amount" class="form-control fw-bold text-success" placeholder="0.00" required min="1" step="0.01">
                </div>

                <button type="submit" class="btn btn-success w-100 btn-lg shadow">Make Receipt <i class="bi bi-printer"></i></button>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="receiptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Receipt</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="receiptBody"></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary" onclick="printReceipt()">Print / Save</button></div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleMode() {
    let mode = document.querySelector('input[name="mode"]:checked').value;
    $('#mode_upi, #mode_bank, #mode_cheque').hide();
    $('.chq-field').prop('required', false);
    if(mode === 'UPI') $('#mode_upi').show();
    else if(mode === 'BankTransfer') $('#mode_bank').show();
    else if(mode === 'Cheque') { $('#mode_cheque').show(); $('.chq-field').prop('required', true); }
}

$('#donationForm').submit(function(e){
    e.preventDefault();
    $.ajax({
        url: 'ajax_handler.php',
        method: 'POST',
        data: $(this).serialize(),
        success: function(resp) {
            $('#receiptBody').html(resp);
            new bootstrap.Modal(document.getElementById('receiptModal')).show();
            document.getElementById("donationForm").reset();
            toggleMode();
        },
        error: function(xhr) { if(xhr.status === 403) { alert(xhr.responseText); window.location.reload(); } else { alert('Error generating receipt.'); } }
    });
});

function printReceipt() {
    var printContent = document.getElementById('receiptBody').innerHTML;
    var win = window.open('', '', 'width=800,height=600');
    win.document.write('<html><head><title>Receipt</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></head><body>' + printContent + '<script>window.onload = function() { window.print(); setTimeout(function(){window.close();}, 100); }<\/script></body></html>');
    win.document.close();
}
</script>
</body>
</html>