<?php
session_start();

// Check if appointment request was successful
if (!isset($_SESSION['appointment_success']) || $_SESSION['appointment_success'] !== true) {
    header('Location: index.php');
    exit;
}

// Clean up session variables
unset($_SESSION['appointment_success']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Request Successful - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <main>
            <section class="success-section">
                <div class="success-message">
                    <h1>Appointment Request Submitted!</h1>
                    <p>Your request for a viewing appointment has been submitted successfully.</p>
                    <p>The owner will be notified of your request and will confirm the appointment.</p>
                    <p>You will receive a notification when the owner accepts your appointment request.</p>
                    
                    <div class="success-actions">
                        <a href="messages.php" class="btn btn-primary">Check Messages</a>
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
