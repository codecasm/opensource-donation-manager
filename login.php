<?php
include 'config.php';

$msg = "";

if (isset($_GET['msg']) && $_GET['msg'] === 'disabled') {
    $msg = "Your account has been disabled. Please contact Admin.";
}

// CLIENT REGISTRATION / LOGIN (OTP)
if (isset($_POST['send_otp'])) {
    verify_csrf();
    $mobile = $_POST['mobile'];
    $name = $_POST['full_name'] ?? '';
    $address = $_POST['address'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE mobile=?");
    $stmt->bind_param("s", $mobile);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 0) {
        if (empty($name)) { 
            $msg = "Please enter Name for registration."; 
        } else {
            $stmt = $conn->prepare("INSERT INTO users (full_name, mobile, address, role, is_active) VALUES (?, ?, ?, 'client', 0)");
            $stmt->bind_param("sss", $name, $mobile, $address);
            $stmt->execute();
            $msg = "Registration successful! Wait for OTP.";
        }
    } else {
        $user_check = $res->fetch_assoc();
        if($user_check['is_active'] == 0 || $user_check['is_deleted'] == 1) {
            $msg = "Your account is inactive or disabled. Please contact Admin.";
        }
    }

    if ((empty($msg) || strpos($msg, 'success') !== false) && (!isset($user_check) || $user_check['is_active'] == 1)) {
        $otp = rand(100000, 999999);
        $conn->query("UPDATE users SET otp_code='$otp' WHERE mobile='$mobile'");
        $_SESSION['temp_mobile'] = $mobile;
        echo "<script>alert('Mock OTP: $otp');</script>";
        $step = 2;
    }
}

if (isset($_POST['verify_otp'])) {
    verify_csrf();
    $otp = $_POST['otp'];
    $mobile = $_SESSION['temp_mobile'];
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE mobile=? AND otp_code=?");
    $stmt->bind_param("ss", $mobile, $otp);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        if ($user['is_active'] == 0 || $user['is_deleted'] == 1) {
            $msg = "Account waiting for Admin Approval.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = 'client';
            $_SESSION['user_name'] = $user['full_name'];
            header("Location: dashboard.php");
            exit();
        }
    } else {
        $msg = "Invalid OTP";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Volunteer Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .auth-card { max-width: 400px; margin: 50px auto; }
    </style>
</head>
<body class="bg-light">
    
    <!-- Include Header for Branding -->
    <?php include 'header.php'; ?>

    <div class="container d-flex flex-column justify-content-center align-items-center" style="min-height: 60vh;">
        <div class="card auth-card shadow w-100">
            <div class="card-body p-4">
                <h5 class="text-center mb-4">Volunteer Access</h5>
                <?php if($msg): ?><div class="alert alert-info small"><?= h($msg) ?></div><?php endif; ?>
                
                <?php if(!isset($step)): ?>
                    <form method="POST">
                        <?= csrf_token_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Mobile Number</label>
                            <input type="text" name="mobile" class="form-control form-control-lg" required maxlength="10" placeholder="Enter 10 digit mobile">
                        </div>
                        <div class="collapse show" id="regFields">
                            <div class="mb-3">
                                <label class="form-label text-muted small">Full Name <span class="text-info">(New Users Only)</span></label>
                                <input type="text" name="full_name" class="form-control" placeholder="Your Name">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">City/Area <span class="text-info">(New Users Only)</span></label>
                                <input type="text" name="address" class="form-control" placeholder="e.g. Mumbai">
                            </div>
                        </div>
                        <button type="submit" name="send_otp" class="btn btn-primary w-100 btn-lg">Send OTP</button>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <?= csrf_token_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Enter OTP sent to <?= h($_SESSION['temp_mobile']) ?></label>
                            <input type="number" name="otp" class="form-control form-control-lg text-center letter-spacing-2" required placeholder="XXXXXX">
                        </div>
                        <button type="submit" name="verify_otp" class="btn btn-success w-100 btn-lg">Verify & Login</button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white text-center py-3">
                <a href="admin_login.php" class="text-decoration-none text-secondary small">Go to Admin Login <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>