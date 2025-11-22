<style>
    .signature-font { font-family: 'Brush Script MT', cursive; font-size: 1.2rem; color: #0d6efd; }
</style>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top mb-4">
    <div class="container-fluid">
        <!-- Brand / Logo Logic -->
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
            <?php 
            // Priority: 1. Specific Org (Dashboard) 2. Global Single Org (Config) 3. Generic
            $displayOrg = isset($org) ? $org : ($GLOBALS['SINGLE_ORG'] ?? null);
            
            if($displayOrg && !empty($displayOrg['logo_path']) && file_exists($displayOrg['logo_path'])): ?>
                <img src="<?= h($displayOrg['logo_path']) ?>" height="30" class="me-2">
            <?php else: ?>
                <i class="bi bi-heart-fill text-primary me-2"></i>
            <?php endif; ?>
            
            <!-- Dynamic Heading Logic -->
            <span class="fw-bold text-dark">
                <?= ($displayOrg && !empty($displayOrg['name'])) ? h($displayOrg['name']) . " Donation" : "Donation App" ?>
            </span>
        </a>

        <div class="d-flex align-items-center">
            <?php if(isset($_SESSION['user_name'])): ?>
                <div class="text-end d-none d-md-block me-3">
                    <small class="text-muted d-block" style="font-size: 0.7rem;">Logged in as</small>
                    <span class="signature-font"><?= h($_SESSION['user_name']) ?></span>
                </div>
                <span class="badge bg-primary rounded-pill d-md-none me-2"><?= h($_SESSION['user_name']) ?></span>
                <a href="logout.php" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i> <span class="d-none d-sm-inline">Logout</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>