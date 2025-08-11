<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once 'database.inc.php';

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

// Get owner details if user is an owner
$owner_details = null;
if ($user['user_type'] === 'owner') {
    $stmt = $pdo->prepare("SELECT * FROM owner_details WHERE owner_id = :owner_id");
    $stmt->execute(['owner_id' => $_SESSION['user_id']]);
    $owner_details = $stmt->fetch();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $errors = [];
    
    // Name validation (only characters)
    if (empty($_POST['name'])) {
        $errors[] = 'Name is required';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $_POST['name'])) {
        $errors[] = 'Name should contain only letters and spaces';
    }
    
    // Address validation
    if (empty($_POST['address_flat'])) {
        $errors[] = 'Flat/House number is required';
    }
    if (empty($_POST['address_street'])) {
        $errors[] = 'Street name is required';
    }
    if (empty($_POST['address_city'])) {
        $errors[] = 'City is required';
    }
    if (empty($_POST['address_postal'])) {
        $errors[] = 'Postal code is required';
    }
    
    // Mobile validation
    if (empty($_POST['mobile'])) {
        $errors[] = 'Mobile number is required';
    }
    
    // Bank details validation for owners
    if ($user['user_type'] === 'owner') {
        if (empty($_POST['bank_name'])) {
            $errors[] = 'Bank name is required';
        }
        if (empty($_POST['bank_branch'])) {
            $errors[] = 'Bank branch is required';
        }
        if (empty($_POST['account_number'])) {
            $errors[] = 'Account number is required';
        }
    }
    
    // If no errors, update user details
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update user details
            $stmt = $pdo->prepare("
                UPDATE users SET
                    name = :name,
                    address_flat = :address_flat,
                    address_street = :address_street,
                    address_city = :address_city,
                    address_postal = :address_postal,
                    mobile = :mobile,
                    telephone = :telephone
                WHERE user_id = :user_id
            ");
            
            $stmt->execute([
                'name' => $_POST['name'],
                'address_flat' => $_POST['address_flat'],
                'address_street' => $_POST['address_street'],
                'address_city' => $_POST['address_city'],
                'address_postal' => $_POST['address_postal'],
                'mobile' => $_POST['mobile'],
                'telephone' => $_POST['telephone'] ?? '',
                'user_id' => $_SESSION['user_id']
            ]);
            
            // Update owner details if user is an owner
            if ($user['user_type'] === 'owner') {
                $stmt = $pdo->prepare("
                    UPDATE owner_details SET
                        bank_name = :bank_name,
                        bank_branch = :bank_branch,
                        account_number = :account_number
                    WHERE owner_id = :owner_id
                ");
                
                $stmt->execute([
                    'bank_name' => $_POST['bank_name'],
                    'bank_branch' => $_POST['bank_branch'],
                    'account_number' => $_POST['account_number'],
                    'owner_id' => $_SESSION['user_id']
                ]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Update session data
            $_SESSION['name'] = $_POST['name'];
            
            // Redirect to profile page with success message
            header('Location: profile.php?success=1');
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = 'Update failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <section class="content-area">
            <section class="profile-section">
                <h1>My Profile</h1>
                
                <?php if (isset($_GET['success'])): ?>
                    <section class="success-message">
                        <p>Your profile has been updated successfully.</p>
                    </section>
                <?php endif; ?>
                
                <?php if (isset($errors) && !empty($errors)): ?>
                    <section class="error-messages">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>
                
                <section class="profile-content">
                    <aside class="profile-sidebar">
                        <section class="profile-photo-container">
                            <img src="<?php echo $user['profile_photo'] ?? 'images/default_profile.png'; ?>" alt="Profile Photo" class="profile-photo-large">
                            <p class="profile-name"><?php echo htmlspecialchars($user['name']); ?></p>
                            <p class="profile-type"><?php echo ucfirst($user['user_type']); ?></p>
                            <p class="profile-id">ID: <?php echo htmlspecialchars($user['user_id']); ?></p>
                        </section>
                        
                        <section class="profile-stats">
                            <?php if ($user['user_type'] === 'customer'): ?>
                                <?php
                                // Get rental stats
                                $stmt = $pdo->prepare("
                                    SELECT 
                                        COUNT(*) as total_rentals,
                                        SUM(CASE WHEN end_date >= CURDATE() THEN 1 ELSE 0 END) as active_rentals
                                    FROM rentals
                                    WHERE customer_id = :customer_id
                                ");
                                $stmt->execute(['customer_id' => $_SESSION['user_id']]);
                                $rental_stats = $stmt->fetch();
                                ?>
                                <section class="stat-item">
                                    <span class="stat-value"><?php echo $rental_stats['total_rentals']; ?></span>
                                    <span class="stat-label">Total Rentals</span>
                                </section>
                                <section class="stat-item">
                                    <span class="stat-value"><?php echo $rental_stats['active_rentals']; ?></span>
                                    <span class="stat-label">Active Rentals</span>
                                </section>
                            <?php elseif ($user['user_type'] === 'owner'): ?>
                                <?php
                                // Get flat stats
                                $stmt = $pdo->prepare("
                                    SELECT 
                                        COUNT(*) as total_flats,
                                        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as active_flats,
                                        SUM(CASE WHEN status = 'rented' THEN 1 ELSE 0 END) as rented_flats
                                    FROM flats
                                    WHERE owner_id = :owner_id
                                ");
                                $stmt->execute(['owner_id' => $_SESSION['user_id']]);
                                $flat_stats = $stmt->fetch();
                                ?>
                                <section class="stat-item">
                                    <span class="stat-value"><?php echo $flat_stats['total_flats']; ?></span>
                                    <span class="stat-label">Total Flats</span>
                                </section>
                                <section class="stat-item">
                                    <span class="stat-value"><?php echo $flat_stats['active_flats']; ?></span>
                                    <span class="stat-label">Active Listings</span>
                                </section>
                                <section class="stat-item">
                                    <span class="stat-value"><?php echo $flat_stats['rented_flats']; ?></span>
                                    <span class="stat-label">Rented Flats</span>
                                </section>
                            <?php endif; ?>
                        </section>
                    </aside>
                    
                    <section class="profile-details">
                        <h2>Edit Profile</h2>
                        
                        <form action="profile.php" method="POST" class="profile-form">
                            <fieldset class="form-group">
                                <label for="user_id">User ID</label>
                                <input type="text" id="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>" readonly>
                                <small>User ID cannot be changed</small>
                            </fieldset>
                            
                            <fieldset class="form-group">
                                <label for="name">Full Name <span class="required">*</span></label>
                                <input type="text" id="name" name="name" required pattern="[A-Za-z\s]+" title="Name should contain only letters and spaces" value="<?php echo htmlspecialchars($user['name']); ?>">
                            </fieldset>
                            
                            <fieldset class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                <small>Email address cannot be changed</small>
                            </fieldset>
                            
                            <fieldset class="form-group">
                                <legend>Address <span class="required">*</span></legend>
                                
                                <section class="form-row">
                                    <fieldset class="form-group">
                                        <label for="address_flat">Flat/House No. <span class="required">*</span></label>
                                        <input type="text" id="address_flat" name="address_flat" required value="<?php echo htmlspecialchars($user['address_flat']); ?>">
                                    </fieldset>
                                    
                                    <fieldset class="form-group">
                                        <label for="address_street">Street Name <span class="required">*</span></label>
                                        <input type="text" id="address_street" name="address_street" required value="<?php echo htmlspecialchars($user['address_street']); ?>">
                                    </fieldset>
                                </section>
                                
                                <section class="form-row">
                                    <fieldset class="form-group">
                                        <label for="address_city">City <span class="required">*</span></label>
                                        <input type="text" id="address_city" name="address_city" required value="<?php echo htmlspecialchars($user['address_city']); ?>">
                                    </fieldset>
                                    
                                    <fieldset class="form-group">
                                        <label for="address_postal">Postal Code <span class="required">*</span></label>
                                        <input type="text" id="address_postal" name="address_postal" required value="<?php echo htmlspecialchars($user['address_postal']); ?>">
                                    </fieldset>
                                </section>
                            </fieldset>
                            
                            <section class="form-row">
                                <fieldset class="form-group">
                                    <label for="mobile">Mobile Number <span class="required">*</span></label>
                                    <input type="tel" id="mobile" name="mobile" required value="<?php echo htmlspecialchars($user['mobile']); ?>">
                                </fieldset>
                                
                                <fieldset class="form-group">
                                    <label for="telephone">Telephone Number</label>
                                    <input type="tel" id="telephone" name="telephone" value="<?php echo htmlspecialchars($user['telephone']); ?>">
                                </fieldset>
                            </section>
                            
                            <?php if ($user['user_type'] === 'owner' && $owner_details): ?>
                                <fieldset class="form-group">
                                    <legend>Bank Details <span class="required">*</span></legend>
                                    
                                    <section class="form-row">
                                        <fieldset class="form-group">
                                            <label for="bank_name">Bank Name <span class="required">*</span></label>
                                            <input type="text" id="bank_name" name="bank_name" required value="<?php echo htmlspecialchars($owner_details['bank_name']); ?>">
                                        </fieldset>
                                        
                                        <fieldset class="form-group">
                                            <label for="bank_branch">Bank Branch <span class="required">*</span></label>
                                            <input type="text" id="bank_branch" name="bank_branch" required value="<?php echo htmlspecialchars($owner_details['bank_branch']); ?>">
                                        </fieldset>
                                    </section>
                                    
                                    <fieldset class="form-group">
                                        <label for="account_number">Account Number <span class="required">*</span></label>
                                        <input type="text" id="account_number" name="account_number" required value="<?php echo htmlspecialchars($owner_details['account_number']); ?>">
                                    </fieldset>
                                </fieldset>
                            <?php endif; ?>
                            
                            <section class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </section>
                        </form>
                    </section>
                </section>
            </section>
        </section>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
