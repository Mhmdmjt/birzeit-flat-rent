<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Initialize step (default to 1)
$step = isset($_SESSION['registration_step']) ? $_SESSION['registration_step'] : 1;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'database.inc.php';
    
    // Step 1: Personal details
    if (isset($_POST['step1'])) {
        // Validate inputs
        $errors = [];
        
        // National ID validation
        if (empty($_POST['national_id'])) {
            $errors[] = 'National ID is required';
        }
        
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
        
        // Date of birth validation
        if (empty($_POST['date_of_birth'])) {
            $errors[] = 'Date of birth is required';
        } elseif (strtotime($_POST['date_of_birth']) > time()) {
            $errors[] = 'Date of birth cannot be in the future';
        }
        
        // Email validation
        if (empty($_POST['email'])) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $stmt->execute(['email' => $_POST['email']]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Email already exists';
            }
        }
        
        // Mobile validation
        if (empty($_POST['mobile'])) {
            $errors[] = 'Mobile number is required';
        }
        
        // Bank details validation
        if (empty($_POST['bank_name'])) {
            $errors[] = 'Bank name is required';
        }
        if (empty($_POST['bank_branch'])) {
            $errors[] = 'Bank branch is required';
        }
        if (empty($_POST['account_number'])) {
            $errors[] = 'Account number is required';
        }
        
        // If no errors, save data to session and proceed to step 2
        if (empty($errors)) {
            $_SESSION['registration_data'] = [
                'national_id' => $_POST['national_id'],
                'name' => $_POST['name'],
                'address_flat' => $_POST['address_flat'],
                'address_street' => $_POST['address_street'],
                'address_city' => $_POST['address_city'],
                'address_postal' => $_POST['address_postal'],
                'date_of_birth' => $_POST['date_of_birth'],
                'email' => $_POST['email'],
                'mobile' => $_POST['mobile'],
                'telephone' => $_POST['telephone'] ?? '',
                'bank_name' => $_POST['bank_name'],
                'bank_branch' => $_POST['bank_branch'],
                'account_number' => $_POST['account_number'],
                'user_type' => 'owner'
            ];
            
            $_SESSION['registration_step'] = 2;
            header('Location: register-owner.php');
            exit;
        }
    }
    
    // Step 2: Account details
    elseif (isset($_POST['step2'])) {
        // Validate inputs
        $errors = [];
        
        // Username validation (must be email)
        if (empty($_POST['username'])) {
            $errors[] = 'Username is required';
        } elseif (!filter_var($_POST['username'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Username must be a valid email address';
        } else {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $stmt->execute(['username' => $_POST['username']]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'Username already exists';
            }
        }
        
        // Password validation
        if (empty($_POST['password'])) {
            $errors[] = 'Password is required';
        } elseif (strlen($_POST['password']) < 6 || strlen($_POST['password']) > 15) {
            $errors[] = 'Password must be between 6 and 15 characters';
        } elseif (!preg_match('/^\d.*[a-z]$/', $_POST['password'])) {
            $errors[] = 'Password must start with a digit and end with a lowercase letter';
        }
        
        // Confirm password
        if ($_POST['password'] !== $_POST['confirm_password']) {
            $errors[] = 'Passwords do not match';
        }
        
        // If no errors, save data to session and proceed to step 3
        if (empty($errors)) {
            $_SESSION['registration_data']['username'] = $_POST['username'];
            $_SESSION['registration_data']['password'] = $_POST['password']; // Will be hashed before storage
            
            $_SESSION['registration_step'] = 3;
            header('Location: register-owner.php');
            exit;
        }
    }
    
    // Step 3: Confirmation
    elseif (isset($_POST['step3'])) {
        // Generate unique 9-digit owner ID
        do {
            $owner_id = '2' . str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
            
            // Check if ID already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $owner_id]);
        } while ($stmt->fetchColumn() > 0);
        
        // Hash password
        $hashed_password = password_hash($_SESSION['registration_data']['password'], PASSWORD_DEFAULT);
        
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert user into database
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    user_id, national_id, name, address_flat, address_street, address_city, address_postal,
                    date_of_birth, email, mobile, telephone, user_type, username, password
                ) VALUES (
                    :user_id, :national_id, :name, :address_flat, :address_street, :address_city, :address_postal,
                    :date_of_birth, :email, :mobile, :telephone, :user_type, :username, :password
                )
            ");
            
            $stmt->execute([
                'user_id' => $owner_id,
                'national_id' => $_SESSION['registration_data']['national_id'],
                'name' => $_SESSION['registration_data']['name'],
                'address_flat' => $_SESSION['registration_data']['address_flat'],
                'address_street' => $_SESSION['registration_data']['address_street'],
                'address_city' => $_SESSION['registration_data']['address_city'],
                'address_postal' => $_SESSION['registration_data']['address_postal'],
                'date_of_birth' => $_SESSION['registration_data']['date_of_birth'],
                'email' => $_SESSION['registration_data']['email'],
                'mobile' => $_SESSION['registration_data']['mobile'],
                'telephone' => $_SESSION['registration_data']['telephone'],
                'user_type' => $_SESSION['registration_data']['user_type'],
                'username' => $_SESSION['registration_data']['username'],
                'password' => $hashed_password
            ]);
            
            // Insert owner details
            $stmt = $pdo->prepare("
                INSERT INTO owner_details (
                    owner_id, bank_name, bank_branch, account_number
                ) VALUES (
                    :owner_id, :bank_name, :bank_branch, :account_number
                )
            ");
            
            $stmt->execute([
                'owner_id' => $owner_id,
                'bank_name' => $_SESSION['registration_data']['bank_name'],
                'bank_branch' => $_SESSION['registration_data']['bank_branch'],
                'account_number' => $_SESSION['registration_data']['account_number']
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            // Registration successful
            $_SESSION['registration_success'] = true;
            $_SESSION['owner_id'] = $owner_id;
            $_SESSION['owner_name'] = $_SESSION['registration_data']['name'];
            
            // Clean up session data
            unset($_SESSION['registration_data']);
            unset($_SESSION['registration_step']);
            
            header('Location: register-success.php');
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Registration - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <main>
            <section class="registration-section">
                <h1>Owner Registration</h1>
                
                <div class="registration-progress">
                    <div class="progress-step <?php echo $step >= 1 ? 'active' : ''; ?>">
                        <span class="step-number">1</span>
                        <span class="step-label">Personal Details</span>
                    </div>
                    <div class="progress-connector"></div>
                    <div class="progress-step <?php echo $step >= 2 ? 'active' : ''; ?>">
                        <span class="step-number">2</span>
                        <span class="step-label">Account Setup</span>
                    </div>
                    <div class="progress-connector"></div>
                    <div class="progress-step <?php echo $step >= 3 ? 'active' : ''; ?>">
                        <span class="step-number">3</span>
                        <span class="step-label">Confirmation</span>
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
                
                <?php if ($step === 1): ?>
                    <!-- Step 1: Personal Details -->
                    <form action="register-owner.php" method="POST" class="registration-form">
                        <div class="form-group">
                            <label for="national_id">National ID Number (هوية رقم) <span class="required">*</span></label>
                            <input type="text" id="national_id" name="national_id" required value="<?php echo isset($_SESSION['registration_data']['national_id']) ? htmlspecialchars($_SESSION['registration_data']['national_id']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="name">Full Name <span class="required">*</span></label>
                            <input type="text" id="name" name="name" required pattern="[A-Za-z\s]+" title="Name should contain only letters and spaces" value="<?php echo isset($_SESSION['registration_data']['name']) ? htmlspecialchars($_SESSION['registration_data']['name']) : ''; ?>">
                        </div>
                        
                        <fieldset class="form-group">
                            <legend>Address <span class="required">*</span></legend>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="address_flat">Flat/House No. <span class="required">*</span></label>
                                    <input type="text" id="address_flat" name="address_flat" required value="<?php echo isset($_SESSION['registration_data']['address_flat']) ? htmlspecialchars($_SESSION['registration_data']['address_flat']) : ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="address_street">Street Name <span class="required">*</span></label>
                                    <input type="text" id="address_street" name="address_street" required value="<?php echo isset($_SESSION['registration_data']['address_street']) ? htmlspecialchars($_SESSION['registration_data']['address_street']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="address_city">City <span class="required">*</span></label>
                                    <input type="text" id="address_city" name="address_city" required value="<?php echo isset($_SESSION['registration_data']['address_city']) ? htmlspecialchars($_SESSION['registration_data']['address_city']) : ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="address_postal">Postal Code <span class="required">*</span></label>
                                    <input type="text" id="address_postal" name="address_postal" required value="<?php echo isset($_SESSION['registration_data']['address_postal']) ? htmlspecialchars($_SESSION['registration_data']['address_postal']) : ''; ?>">
                                </div>
                            </div>
                        </fieldset>
                        
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth <span class="required">*</span></label>
                            <input type="date" id="date_of_birth" name="date_of_birth" required value="<?php echo isset($_SESSION['registration_data']['date_of_birth']) ? htmlspecialchars($_SESSION['registration_data']['date_of_birth']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <input type="email" id="email" name="email" required value="<?php echo isset($_SESSION['registration_data']['email']) ? htmlspecialchars($_SESSION['registration_data']['email']) : ''; ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="mobile">Mobile Number <span class="required">*</span></label>
                                <input type="tel" id="mobile" name="mobile" required value="<?php echo isset($_SESSION['registration_data']['mobile']) ? htmlspecialchars($_SESSION['registration_data']['mobile']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="telephone">Telephone Number</label>
                                <input type="tel" id="telephone" name="telephone" value="<?php echo isset($_SESSION['registration_data']['telephone']) ? htmlspecialchars($_SESSION['registration_data']['telephone']) : ''; ?>">
                            </div>
                        </div>
                        
                        <fieldset class="form-group">
                            <legend>Bank Details <span class="required">*</span></legend>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="bank_name">Bank Name <span class="required">*</span></label>
                                    <input type="text" id="bank_name" name="bank_name" required value="<?php echo isset($_SESSION['registration_data']['bank_name']) ? htmlspecialchars($_SESSION['registration_data']['bank_name']) : ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="bank_branch">Bank Branch <span class="required">*</span></label>
                                    <input type="text" id="bank_branch" name="bank_branch" required value="<?php echo isset($_SESSION['registration_data']['bank_branch']) ? htmlspecialchars($_SESSION['registration_data']['bank_branch']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="account_number">Account Number <span class="required">*</span></label>
                                <input type="text" id="account_number" name="account_number" required value="<?php echo isset($_SESSION['registration_data']['account_number']) ? htmlspecialchars($_SESSION['registration_data']['account_number']) : ''; ?>">
                            </div>
                        </fieldset>
                        
                        <div class="form-actions">
                            <button type="submit" name="step1" class="btn btn-primary">Next Step</button>
                        </div>
                    </form>
                
                <?php elseif ($step === 2): ?>
                    <!-- Step 2: Account Setup -->
                    <form action="register-owner.php" method="POST" class="registration-form">
                        <div class="form-group">
                            <label for="username">Username (Email Address) <span class="required">*</span></label>
                            <input type="email" id="username" name="username" required value="<?php echo isset($_SESSION['registration_data']['username']) ? htmlspecialchars($_SESSION['registration_data']['username']) : $_SESSION['registration_data']['email']; ?>">
                            <small>Your email address will be used as your username</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password <span class="required">*</span></label>
                            <input type="password" id="password" name="password" required minlength="6" maxlength="15">
                            <small>Password must be 6-15 characters, start with a digit, and end with a lowercase letter</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6" maxlength="15">
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" onclick="window.location.href='register-owner.php?step=1'" class="btn btn-secondary">Previous Step</button>
                            <button type="submit" name="step2" class="btn btn-primary">Next Step</button>
                        </div>
                    </form>
                
                <?php elseif ($step === 3): ?>
                    <!-- Step 3: Confirmation -->
                    <form action="register-owner.php" method="POST" class="registration-form">
                        <h2>Review Your Information</h2>
                        
                        <div class="review-details">
                            <div class="review-group">
                                <h3>Personal Details</h3>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['name']); ?></p>
                                <p><strong>National ID:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['national_id']); ?></p>
                                <p><strong>Date of Birth:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['date_of_birth']); ?></p>
                            </div>
                            
                            <div class="review-group">
                                <h3>Contact Information</h3>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['email']); ?></p>
                                <p><strong>Mobile:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['mobile']); ?></p>
                                <?php if (!empty($_SESSION['registration_data']['telephone'])): ?>
                                    <p><strong>Telephone:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['telephone']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="review-group">
                                <h3>Address</h3>
                                <p>
                                    <?php echo htmlspecialchars($_SESSION['registration_data']['address_flat']); ?>,
                                    <?php echo htmlspecialchars($_SESSION['registration_data']['address_street']); ?>,
                                    <?php echo htmlspecialchars($_SESSION['registration_data']['address_city']); ?>,
                                    <?php echo htmlspecialchars($_SESSION['registration_data']['address_postal']); ?>
                                </p>
                            </div>
                            
                            <div class="review-group">
                                <h3>Bank Details</h3>
                                <p><strong>Bank Name:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['bank_name']); ?></p>
                                <p><strong>Bank Branch:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['bank_branch']); ?></p>
                                <p><strong>Account Number:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['account_number']); ?></p>
                            </div>
                            
                            <div class="review-group">
                                <h3>Account Information</h3>
                                <p><strong>Username:</strong> <?php echo htmlspecialchars($_SESSION['registration_data']['username']); ?></p>
                                <p><strong>Password:</strong> ********</p>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" onclick="window.location.href='register-owner.php?step=2'" class="btn btn-secondary">Previous Step</button>
                            <button type="submit" name="step3" class="btn btn-primary">Confirm Registration</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
