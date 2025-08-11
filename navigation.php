<nav class="main-nav-container">
    <ul class="main-nav">
        <li><a href="index.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'class="active"' : ''; ?>>Home</a></li>
        <li><a href="search.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'search.php') ? 'class="active"' : ''; ?>>Search Flats</a></li>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <li><a href="messages.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'messages.php') ? 'class="active"' : ''; ?>>Messages</a></li>
            
            <?php if ($_SESSION['user_type'] === 'customer'): ?>
                <li><a href="rented-flats.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'rented-flats.php') ? 'class="active"' : ''; ?>>My Rentals</a></li>
            <?php endif; ?>
            
            <?php if ($_SESSION['user_type'] === 'owner'): ?>
                <li><a href="offer-flat.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'offer-flat.php') ? 'class="active"' : ''; ?>>Offer Flat</a></li>
                <li><a href="my-flats.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'my-flats.php') ? 'class="active"' : ''; ?>>My Flats</a></li>
            <?php endif; ?>
            
            <?php if ($_SESSION['user_type'] === 'manager'): ?>
                <li><a href="flat-inquire.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'flat-inquire.php') ? 'class="active"' : ''; ?>>Flat Inquire</a></li>
                <li><a href="approve-flats.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'approve-flats.php') ? 'class="active"' : ''; ?>>Approve Flats</a></li>
            <?php endif; ?>
        <?php endif; ?>
        
        <li><a href="about.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'about.php') ? 'class="active"' : ''; ?>>About Us</a></li>
    </ul>
</nav>
