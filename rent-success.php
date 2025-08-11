<?php
session_start();

// Check if rental was successful
if (!isset($_SESSION['rental_success']) || $_SESSION['rental_success'] !== true) {
    header('Location: index.php');
    exit;
}

// Get rental details
$rental_id = $_SESSION['rental_id'];
$owner_name = $_SESSION['owner_name'];
$owner_mobile = $_SESSION['owner_mobile'];

// Clean up session variables
unset($_SESSION['rental_success']);
unset($_SESSION['rental_id']);
unset($_SESSION['owner_name']);
unset($_SESSION['owner_mobile']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rental Successful - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <main>
            <section class="success-section">
                <div class="success-message">
                    <h1>Rental Successful!</h1>
                    <p>Your flat rental has been confirmed. The rental ID is: <strong><?php echo htmlspecialchars($rental_id); ?></strong></p>
                    <p>You can collect the keys from the owner:</p>
                    <div class="owner-details">
                        <p><strong>Owner Name:</strong> <?php echo htmlspecialchars($owner_name); ?></p>
                        <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($owner_mobile); ?></p>
                    </div>
                    <p>Please contact the owner to arrange key collection and move-in details.</p>
                    
                    <div class="success-actions">
                        <a href="rented-flats.php" class="btn btn-primary">View My Rentals</a>
                        <a href="search.php" class="btn btn-secondary">Search More Flats</a>
                        <a href="index.php" class="btn btn-secondary">Return to Home Page</a>
                    </div>
                </div>
            </section>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
