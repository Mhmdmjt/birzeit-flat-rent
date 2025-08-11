<?php
session_start();

// Check if registration was successful
if (!isset($_SESSION['registration_success']) || $_SESSION['registration_success'] !== true) {
    header('Location: index.php');
    exit;
}

// Get user details
$user_id = isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : $_SESSION['owner_id'];
$name = isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : $_SESSION['owner_name'];
$user_type = isset($_SESSION['customer_id']) ? 'customer' : 'owner';

// Clean up session variables
unset($_SESSION['registration_success']);
unset($_SESSION['customer_id']);
unset($_SESSION['customer_name']);
unset($_SESSION['owner_id']);
unset($_SESSION['owner_name']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <main>
            <section class="success-section">
                <div class="success-message">
                    <h1>Registration Successful!</h1>
                    <p>Congratulations, <?php echo htmlspecialchars($name); ?>! Your account has been created successfully.</p>
                    <p>Your <?php echo $user_type; ?> ID is: <strong><?php echo htmlspecialchars($user_id); ?></strong></p>
                    <p>Please keep this ID for your records as it will be used for future transactions.</p>
                    
                    <div class="success-actions">
                        <a href="login.php" class="btn btn-primary">Login to Your Account</a>
                        <a href="index.php" class="btn btn-secondary">Return to Home Page</a>
                    </div>
                </div>
            </section>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
