<?php
session_start();
require_once 'database.inc.php';

// Check if user ID is provided
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_GET['id'];

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);

if ($stmt->rowCount() === 0) {
    header('Location: index.php');
    exit;
}

$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Card - <?php echo htmlspecialchars($user['name']); ?> - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="user-card-page">
    <div class="user-card <?php echo $user['user_type']; ?>-card">
        <div class="user-card-header">
            <img src="<?php echo $user['profile_photo'] ?? 'images/default_profile.png'; ?>" alt="<?php echo htmlspecialchars($user['name']); ?>" class="user-photo">
            <h1><?php echo htmlspecialchars($user['name']); ?></h1>
            <p class="user-type"><?php echo ucfirst($user['user_type']); ?></p>
        </div>
        
        <div class="user-card-body">
            <p class="user-city"><?php echo htmlspecialchars($user['address_city']); ?></p>
            
            <div class="user-contact">
                <p class="user-phone">
                    <span class="icon">📱</span>
                    <?php echo htmlspecialchars($user['mobile']); ?>
                </p>
                
                <p class="user-email">
                    <span class="icon">✉️</span>
                    <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>"><?php echo htmlspecialchars($user['email']); ?></a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
