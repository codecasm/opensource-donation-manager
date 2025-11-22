<?php
include 'config.php';

$msg = "";

// ADMIN LOGIN
if (isset($_POST['admin_login'])) {
    verify_csrf();
    $mobile = $_POST['mobile'];
    $pass = $_POST['password']; 

    // In production, hash passwords!
    $stmt = $conn->prepare("SELECT * FROM users WHERE mobile=? AND role='admin'");
    $stmt->bind_param("s", $mobile);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        // Simple password check (Demo only)
        // if (password_verify($pass, $user['password'])) { ... }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = 'admin';
        $_SESSION['user_name'] = $user['full_name'];
        header("Location: admin.php");
        exit();
    } else {
        $msg = "Invalid Admin Credentials";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .auth-card { max-width: 400px; margin: 50px auto; }
    </style>
</head>
<body class="bg-dark">
    <div class="container d-flex flex-column justify-content-center align-items-center" style="min-height: 80vh;">
        
        <div class="card auth-card shadow w-100">
            <div class="card-header bg-white text-center py-4">
                <h4 class="mb-0 text-dark"><i class="bi bi-shield-lock-fill"></i> Admin Console</h4>
            </div>
            <div class="card-body p-4">
                <?php if($msg): ?><div class="alert alert-danger small"><?= h($msg) ?></div><?php endif; ?>
                
                <form method="POST">
                    <?= csrf_token_field() ?>
                    <div class="mb-3">
                        <label class="form-label">Mobile / Username</label>
                        <input type="text" name="mobile" class="form-control" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" name="admin_login" class="btn btn-dark w-100">Secure Login</button>
                </form>
            </div>
            <div class="card-footer bg-white text-center py-3">
                <a href="login.php" class="text-decoration-none text-primary small"><i class="bi bi-arrow-left"></i> Back to Volunteer Login</a>
            </div>
        </div>
    </div>

    <footer class="text-center py-3 mt-auto border-top border-secondary">
        <small class="text-white-50">
            Created with <i class="bi bi-heart-fill text-danger" style="font-size: 0.8rem;"></i> by 
            <a href="https://codecasm.com" target="_blank" class="text-decoration-none fw-bold text-light">CodeCasm.com</a>
        </small>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>