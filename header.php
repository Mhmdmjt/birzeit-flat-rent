<header class="main-header">
   <section class="logo-container">
    <a href="index.php">
        <img src="images/logo.png" alt="Birzeit Flat Rent Logo" class="logo">
    </a>
    <h1 class="company-name">Birzeit Flat Rent</h1>
</section>

    
    <section class="header-links">
        <a href="about.php">About Us</a>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <section class="user-card">
                <a href="profile.php">
                    <img src="<?php echo $_SESSION['profile_photo'] ?? 'images/default_profile.png'; ?>" alt="Profile Photo" class="profile-photo">
                    <span><?php echo $_SESSION['name']; ?></span>
                </a>
            </section>
            
            <?php if ($_SESSION['user_type'] === 'customer'): ?>
                <a href="basket.php" class="basket-link">
                    <span class="basket-icon">🛒</span>
                    <?php
                    // Count items in basket
                    require_once 'database.inc.php';
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM basket_items WHERE customer_id = :customer_id");
                    $stmt->execute(['customer_id' => $_SESSION['user_id']]);
                    $count = $stmt->fetchColumn();
                    
                    if ($count > 0) {
                        echo '<span class="basket-count">' . $count . '</span>';
                    }
                    ?>
                </a>
            <?php endif; ?>
            
            <a href="logout.php" class="auth-link">Logout</a>
        <?php else: ?>
            <a href="login.php" class="auth-link">Login</a>
            <a href="register-customer.php" class="auth-link">Sign Up</a>
        <?php endif; ?>
    </section>
</header>
