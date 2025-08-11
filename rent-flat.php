<?php
session_start();
require_once 'database.inc.php';

// Check if flat reference is provided
if (!isset($_GET['ref'])) {
    header('Location: search.php');
    exit;
}

$flat_ref = $_GET['ref'];

// Check if user is logged in as a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    // Redirect to login page with return URL
    $_SESSION['rent_flat_ref'] = $flat_ref; // Save flat reference for after login
    header('Location: login.php?redirect=' . urlencode('rent-flat.php?ref=' . $flat_ref));
    exit;
}

// Get flat details
$stmt = $pdo->prepare("
    SELECT f.*, u.name as owner_name, u.user_id as owner_id, u.mobile as owner_mobile
    FROM flats f
    JOIN users u ON f.owner_id = u.user_id
    WHERE f.flat_ref = :flat_ref AND f.status = 'approved'
");
$stmt->execute(['flat_ref' => $flat_ref]);

if ($stmt->rowCount() === 0) {
    header('Location: search.php');
    exit;
}

$flat = $stmt->fetch();

// Get customer details
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$customer = $stmt->fetch();

// Initialize step (default to 1)
$step = isset($_SESSION['rent_step']) ? $_SESSION['rent_step'] : 1;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Step 1: Rental period
    if (isset($_POST['step1'])) {
        // Validate inputs
        $errors = [];
        
        // Start date validation
        if (empty($_POST['start_date'])) {
            $errors[] = 'Start date is required';
        } elseif (strtotime($_POST['start_date']) < strtotime($flat['available_from'])) {
            $errors[] = 'Start date cannot be before the flat is available';
        } elseif (strtotime($_POST['start_date']) > strtotime($flat['available_to'])) {
            $errors[] = 'Start date cannot be after the flat is no longer available';
        }
        
        // End date validation
        if (empty($_POST['end_date'])) {
            $errors[] = 'End date is required';
        } elseif (strtotime($_POST['end_date']) < strtotime($_POST['start_date'])) {
            $errors[] = 'End date cannot be before start date';
        } elseif (strtotime($_POST['end_date']) > strtotime($flat['available_to'])) {
            $errors[] = 'End date cannot be after the flat is no longer available';
        }
        
        // Check if flat is already rented for the selected period
        if (empty($errors)) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM rentals
                WHERE flat_ref = :flat_ref
                AND status IN ('confirmed', 'pending')
                AND (
                    (start_date <= :start_date AND end_date >= :start_date) OR
                    (start_date <= :end_date AND end_date >= :end_date) OR
                    (start_date >= :start_date AND end_date <= :end_date)
                )
            ");
            
            $stmt->execute([
                'flat_ref' => $flat_ref,
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date']
            ]);
            
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'The flat is already rented for part or all of the selected period';
            }
        }
        
        // If no errors, save data to session and proceed to step 2
        if (empty($errors)) {
            $_SESSION['rent_data'] = [
                'flat_ref' => $flat_ref,
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date']
            ];
            
            // Calculate total cost
            $start = new DateTime($_POST['start_date']);
            $end = new DateTime($_POST['end_date']);
            $interval = $start->diff($end);
            $days = $interval->days + 1; // Include both start and end days
            $months = ceil($days / 30);
            $total_cost = $flat['monthly_cost'] * $months;
            
            $_SESSION['rent_data']['total_cost'] = $total_cost;
            $_SESSION['rent_step'] = 2;
            header('Location: rent-flat.php?ref=' . $flat_ref);
            exit;
        }
    }
    
    // Step 2: Payment details and confirmation
    elseif (isset($_POST['step2'])) {
        // Validate inputs
        $errors = [];
        
        // Credit card validation
        if (empty($_POST['card_number'])) {
            $errors[] = 'Credit card number is required';
        } elseif (!preg_match('/^\d{9}$/', $_POST['card_number'])) {
            $errors[] = 'Credit card number must be 9 digits';
        }
        
        if (empty($_POST['card_expiry'])) {
            $errors[] = 'Expiry date is required';
        } elseif (!preg_match('/^\d{2}\/\d{4}$/', $_POST['card_expiry'])) {
            $errors[] = 'Expiry date must be in MM/YYYY format';
        } else {
            list($month, $year) = explode('/', $_POST['card_expiry']);
            $expiry_date = new DateTime("$year-$month-01");
            $today = new DateTime();
            
            if ($expiry_date < $today) {
                $errors[] = 'Card has expired';
            }
        }
        
        if (empty($_POST['card_name'])) {
            $errors[] = 'Name on card is required';
        }
        
        // If no errors, process the rental
        if (empty($errors)) {
            try {
                // Start transaction
                $pdo->beginTransaction();
                
                // Insert rental into database
                $stmt = $pdo->prepare("
                    INSERT INTO rentals (
                        flat_ref, customer_id, start_date, end_date, total_cost,
                        payment_card_number, payment_card_expiry, payment_card_name, status
                    ) VALUES (
                        :flat_ref, :customer_id, :start_date, :end_date, :total_cost,
                        :payment_card_number, :payment_card_expiry, :payment_card_name, 'confirmed'
                    )
                ");
                
                $stmt->execute([
                    'flat_ref' => $_SESSION['rent_data']['flat_ref'],
                    'customer_id' => $_SESSION['user_id'],
                    'start_date' => $_SESSION['rent_data']['start_date'],
                    'end_date' => $_SESSION['rent_data']['end_date'],
                    'total_cost' => $_SESSION['rent_data']['total_cost'],
                    'payment_card_number' => $_POST['card_number'],
                    'payment_card_expiry' => $_POST['card_expiry'],
                    'payment_card_name' => $_POST['card_name']
                ]);
                
                $rental_id = $pdo->lastInsertId();
                
                // Update flat status to rented
                $stmt = $pdo->prepare("UPDATE flats SET status = 'rented' WHERE flat_ref = :flat_ref");
                $stmt->execute(['flat_ref' => $_SESSION['rent_data']['flat_ref']]);
                
                // Create notification for owner
                $stmt = $pdo->prepare("
                    INSERT INTO messages (
                        sender_id, receiver_id, title, body, related_entity_type, related_entity_id
                    ) VALUES (
                        :sender_id, :receiver_id, :title, :body, 'rental', :rental_id
                    )
                ");
                
                $stmt->execute([
                    'sender_id' => $_SESSION['user_id'],
                    'receiver_id' => $flat['owner_id'],
                    'title' => 'New Flat Rental',
                    'body' => 'Your flat (Ref: ' . $_SESSION['rent_data']['flat_ref'] . ') has been rented by ' . $customer['name'] . ' from ' . $_SESSION['rent_data']['start_date'] . ' to ' . $_SESSION['rent_data']['end_date'] . '.',
                    'rental_id' => $rental_id
                ]);
                
                // Create notification for manager
                $stmt = $pdo->prepare("
                    INSERT INTO messages (
                        sender_id, receiver_id, title, body, related_entity_type, related_entity_id
                    ) SELECT 
                        :sender_id, user_id, :title, :body, 'rental', :rental_id
                    FROM users
                    WHERE user_type = 'manager'
                    LIMIT 1
                ");
                
                $stmt->execute([
                    'sender_id' => $_SESSION['user_id'],
                    'title' => 'New Flat Rental',
                    'body' => 'Flat (Ref: ' . $_SESSION['rent_data']['flat_ref'] . ') has been rented by ' . $customer['name'] . ' from ' . $_SESSION['rent_data']['start_date'] . ' to ' . $_SESSION['rent_data']['end_date'] . '.',
                    'rental_id' => $rental_id
                ]);
                
                // Commit transaction
                $pdo->commit();
                
                // Rental successful
                $_SESSION['rental_success'] = true;
                $_SESSION['rental_id'] = $rental_id;
                $_SESSION['owner_name'] = $flat['owner_name'];
                $_SESSION['owner_mobile'] = $flat['owner_mobile'];
                
                // Clean up session data
                unset($_SESSION['rent_data']);
                unset($_SESSION['rent_step']);
                
                header('Location: rent-success.php');
                exit;
                
            } catch (PDOException $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                $errors[] = 'Rental failed: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent Flat - <?php echo $flat['flat_ref']; ?> - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <main>
            <section class="rent-section">
                <h1>Rent Flat - Ref: <?php echo $flat['flat_ref']; ?></h1>
                
                <div class="rent-progress">
                    <div class="progress-step <?php echo $step >= 1 ? 'active' : ''; ?>">
                        <span class="step-number">1</span>
                        <span class="step-label">Rental Period</span>
                    </div>
                    <div class="progress-connector"></div>
                    <div class="progress-step <?php echo $step >= 2 ? 'active' : ''; ?>">
                        <span class="step-number">2</span>
                        <span class="step-label">Payment & Confirmation</span>
                    </div>
                </div>
                
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="error-messages">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="flat-summary">
                    <h2>Flat Summary</h2>
                    <div class="summary-details">
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($flat['location']); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($flat['address']); ?></p>
                        <p><strong>Monthly Cost:</strong> $<?php echo number_format($flat['monthly_cost'], 2); ?></p>
                        <p><strong>Bedrooms:</strong> <?php echo $flat['bedrooms']; ?></p>
                        <p><strong>Bathrooms:</strong> <?php echo $flat['bathrooms']; ?></p>
                        <p><strong>Available From:</strong> <?php echo date('d/m/Y', strtotime($flat['available_from'])); ?></p>
                        <p><strong>Available To:</strong> <?php echo date('d/m/Y', strtotime($flat['available_to'])); ?></p>
                        <p><strong>Owner:</strong> <?php echo htmlspecialchars($flat['owner_name']); ?></p>
                    </div>
                </div>
                
                <?php if ($step === 1): ?>
                    <!-- Step 1: Rental Period -->
                    <form action="rent-flat.php?ref=<?php echo $flat_ref; ?>" method="POST" class="rent-form">
                        <h2>Select Rental Period</h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_date">Start Date <span class="required">*</span></label>
                                <input type="date" id="start_date" name="start_date" required 
                                       min="<?php echo $flat['available_from']; ?>" 
                                       max="<?php echo $flat['available_to']; ?>"
                                       value="<?php echo isset($_SESSION['rent_data']['start_date']) ? $_SESSION['rent_data']['start_date'] : $flat['available_from']; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date">End Date <span class="required">*</span></label>
                                <input type="date" id="end_date" name="end_date" required 
                                       min="<?php echo $flat['available_from']; ?>" 
                                       max="<?php echo $flat['available_to']; ?>"
                                       value="<?php echo isset($_SESSION['rent_data']['end_date']) ? $_SESSION['rent_data']['end_date'] : $flat['available_to']; ?>">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="flat-detail.php?ref=<?php echo $flat_ref; ?>" class="btn btn-secondary">Back to Flat Details</a>
                            <button type="submit" name="step1" class="btn btn-primary">Next Step</button>
                        </div>
                    </form>
                
                <?php elseif ($step === 2): ?>
                    <!-- Step 2: Payment Details and Confirmation -->
                    <form action="rent-flat.php?ref=<?php echo $flat_ref; ?>" method="POST" class="rent-form">
                        <h2>Rental Summary</h2>
                        
                        <div class="rental-summary">
                            <p><strong>Flat Reference:</strong> <?php echo $flat['flat_ref']; ?></p>
                            <p><strong>Rental Period:</strong> <?php echo date('d/m/Y', strtotime($_SESSION['rent_data']['start_date'])); ?> to <?php echo date('d/m/Y', strtotime($_SESSION['rent_data']['end_date'])); ?></p>
                            <p><strong>Total Cost:</strong> $<?php echo number_format($_SESSION['rent_data']['total_cost'], 2); ?></p>
                        </div>
                        
                        <h2>Payment Details</h2>
                        
                        <div class="form-group">
                            <label for="card_number">Credit Card Number <span class="required">*</span></label>
                            <input type="text" id="card_number" name="card_number" required pattern="\d{9}" title="Credit card number must be 9 digits">
                            <small>Must be 9 digits</small>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="card_expiry">Expiry Date (MM/YYYY) <span class="required">*</span></label>
                                <input type="text" id="card_expiry" name="card_expiry" required pattern="\d{2}/\d{4}" title="Expiry date must be in MM/YYYY format" placeholder="MM/YYYY">
                            </div>
                            
                            <div class="form-group">
                                <label for="card_name">Name on Card <span class="required">*</span></label>
                                <input type="text" id="card_name" name="card_name" required>
                            </div>
                        </div>
                        
                        <div class="form-info">
                            <p>By confirming this rental, you agree to the terms and conditions of Birzeit Flat Rent.</p>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" onclick="window.location.href='rent-flat.php?ref=<?php echo $flat_ref; ?>&step=1'" class="btn btn-secondary">Previous Step</button>
                            <button type="submit" name="step2" class="btn btn-primary">Confirm Rental</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
