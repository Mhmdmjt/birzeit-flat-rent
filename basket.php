<?php
session_start();

// Check if user is logged in as a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once 'database.inc.php';

// Process basket actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Remove item from basket
    if (isset($_POST['remove_item']) && isset($_POST['basket_id'])) {
        $stmt = $pdo->prepare("DELETE FROM basket_items WHERE basket_id = :basket_id AND customer_id = :customer_id");
        $stmt->execute([
            'basket_id' => $_POST['basket_id'],
            'customer_id' => $_SESSION['user_id']
        ]);
        
        header('Location: basket.php?removed=1');
        exit;
    }
    
    // Proceed to checkout
    elseif (isset($_POST['checkout']) && isset($_POST['basket_id'])) {
        $basket_id = $_POST['basket_id'];
        
        // Get basket item details
        $stmt = $pdo->prepare("
            SELECT * FROM basket_items 
            WHERE basket_id = :basket_id AND customer_id = :customer_id
        ");
        $stmt->execute([
            'basket_id' => $basket_id,
            'customer_id' => $_SESSION['user_id']
        ]);
        
        $basket_item = $stmt->fetch();
        
        if ($basket_item) {
            // Redirect to rent page with flat reference
            header('Location: rent-flat.php?ref=' . $basket_item['flat_ref']);
            exit;
        }
    }
}

// Get basket items
$stmt = $pdo->prepare("
    SELECT b.*, f.location, f.monthly_cost, f.bedrooms, f.bathrooms, p.photo_path
    FROM basket_items b
    JOIN flats f ON b.flat_ref = f.flat_ref
    LEFT JOIN flat_photos p ON f.flat_ref = p.flat_ref AND p.is_primary = 1
    WHERE b.customer_id = :customer_id
    ORDER BY b.created_at DESC
");
$stmt->execute(['customer_id' => $_SESSION['user_id']]);
$basket_items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Basket - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <main>
            <section class="basket-section">
                <h1>Shopping Basket</h1>
                
                <?php if (isset($_GET['removed'])): ?>
                    <div class="success-message">
                        <p>Item has been removed from your basket.</p>
                    </div>
                <?php endif; ?>
                
                <?php if (count($basket_items) > 0): ?>
                    <div class="basket-items">
                        <?php foreach ($basket_items as $item): ?>
                            <div class="basket-item">
                                <div class="basket-item-image">
                                    <?php if (!empty($item['photo_path'])): ?>
                                        <img src="<?php echo $item['photo_path']; ?>" alt="Flat <?php echo $item['flat_ref']; ?>">
                                    <?php else: ?>
                                        <img src="/placeholder.svg?height=150&width=200" alt="No image available">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="basket-item-details">
                                    <h2>Flat Ref: <?php echo htmlspecialchars($item['flat_ref']); ?></h2>
                                    <p class="location"><?php echo htmlspecialchars($item['location']); ?></p>
                                    <p class="price">$<?php echo number_format($item['monthly_cost'], 2); ?> per month</p>
                                    <p><?php echo $item['bedrooms']; ?> Bedrooms | <?php echo $item['bathrooms']; ?> Bathrooms</p>
                                    <p class="rental-period">
                                        <strong>Rental Period:</strong> 
                                        <?php echo date('d/m/Y', strtotime($item['start_date'])); ?> to 
                                        <?php echo date('d/m/Y', strtotime($item['end_date'])); ?>
                                    </p>
                                    
                                    <div class="basket-item-actions">
                                        <form action="basket.php" method="POST" class="inline-form">
                                            <input type="hidden" name="basket_id" value="<?php echo $item['basket_id']; ?>">
                                            <button type="submit" name="checkout" class="btn btn-primary">Proceed to Checkout</button>
                                            <button type="submit" name="remove_item" class="btn btn-danger">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-basket">
                        <p>Your shopping basket is empty.</p>
                        <a href="search.php" class="btn btn-primary">Browse Flats</a>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
