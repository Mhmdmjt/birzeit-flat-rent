<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Check if there's a redirect URL
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'database.inc.php';
    
    $username = $_POST['username'];
    $password = $_POST['password'];
    $errors = [];
    
    // Validate inputs
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    // If no validation errors, attempt login
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['profile_photo'] = $user['profile_photo'];
                
                // Redirect to the requested page or index
                header('Location: ' . $redirect);
                exit;
            } else {
                $errors[] = 'Invalid username or password';
            }
        } else {
            $errors[] = 'Invalid username or password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <main>
            <section class="login-section">
                <h1>Login to Your Account</h1>
                
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="error-messages">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : ''; ?>" method="POST" class="login-form">
                    <div class="form-group">
                        <label for="username">Username (Email) <span class="required">*</span></label>
                        <input type="email" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                </form>
                
                <div class="login-footer">
                    <p>Don't have an account?</p>
                    <div class="register-options">
                        <a href="register-customer.php" class="btn btn-secondary">Register as Customer</a>
                        <a href="register-owner.php" class="btn btn-secondary">Register as Owner</a>
                    </div>
                </div>
            </section>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
