<?php
session_start();

// Check if offer was successful
if (!isset($_SESSION['offer_success']) || $_SESSION['offer_success'] !== true) {
    header('Location: index.php');
    exit;
}

// Get flat reference
$flat_ref = $_SESSION['flat_ref'];

// Clean up session variables
unset($_SESSION['offer_success']);
unset($_SESSION['flat_ref']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flat Submitted Successfully - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <main>
            <section class="success-section">
                <div class="success-message">
                    <h1>Flat Submitted Successfully!</h1>
                    <p>Your flat has been submitted for approval. The flat reference number is: <strong><?php echo htmlspecialchars($flat_ref); ?></strong></p>
                    <p>Please keep this reference number for your records. A manager will review your submission and approve it soon.</p>
                    <p>You will receive a notification when your flat is approved and listed on the website.</p>
                    
                    <div class="success-actions">
                        <a href="my-flats.php" class="btn btn-primary">View My Flats</a>
                        <a href="offer-flat.php" class="btn btn-secondary">Offer Another Flat</a>
                        <a href="index.php" class="btn btn-secondary">Return to Home Page</a>
                    </div>
                </div>
            </section>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
