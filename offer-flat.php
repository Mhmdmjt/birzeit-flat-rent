<?php
session_start();

// Check if user is logged in and is an owner
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'owner') {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Initialize step (default to 1)
$step = isset($_SESSION['offer_step']) ? $_SESSION['offer_step'] : 1;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'database.inc.php';
    
    // Step 1: Flat details
    if (isset($_POST['step1'])) {
        // Validate inputs
        $errors = [];
        
        // Basic flat details validation
        if (empty($_POST['location'])) {
            $errors[] = 'Location is required';
        }
        
        if (empty($_POST['address'])) {
            $errors[] = 'Address is required';
        }
        
        if (empty($_POST['monthly_cost']) || !is_numeric($_POST['monthly_cost']) || $_POST['monthly_cost'] <= 0) {
            $errors[] = 'Valid monthly cost is required';
        }
        
        if (empty($_POST['available_from'])) {
            $errors[] = 'Available from date is required';
        }
        
        if (empty($_POST['available_to'])) {
            $errors[] = 'Available to date is required';
        } elseif ($_POST['available_to'] <= $_POST['available_from']) {
            $errors[] = 'Available to date must be after available from date';
        }
        
        if (empty($_POST['bedrooms']) || !is_numeric($_POST['bedrooms']) || $_POST['bedrooms'] <= 0) {
            $errors[] = 'Number of bedrooms is required';
        }
        
        if (empty($_POST['bathrooms']) || !is_numeric($_POST['bathrooms']) || $_POST['bathrooms'] <= 0) {
            $errors[] = 'Number of bathrooms is required';
        }
        
        if (empty($_POST['size_sqm']) || !is_numeric($_POST['size_sqm']) || $_POST['size_sqm'] <= 0) {
            $errors[] = 'Size in square meters is required';
        }
        
        // If no errors, save data to session and proceed to step 2
        if (empty($errors)) {
            $_SESSION['offer_data'] = [
                'location' => $_POST['location'],
                'address' => $_POST['address'],
                'monthly_cost' => $_POST['monthly_cost'],
                'available_from' => $_POST['available_from'],
                'available_to' => $_POST['available_to'],
                'bedrooms' => $_POST['bedrooms'],
                'bathrooms' => $_POST['bathrooms'],
                'size_sqm' => $_POST['size_sqm'],
                'has_heating' => isset($_POST['has_heating']) ? 1 : 0,
                'has_ac' => isset($_POST['has_ac']) ? 1 : 0,
                'has_access_control' => isset($_POST['has_access_control']) ? 1 : 0,
                'has_parking' => isset($_POST['has_parking']) ? 1 : 0,
                'has_backyard' => $_POST['has_backyard'] ?? 'none',
                'has_playground' => isset($_POST['has_playground']) ? 1 : 0,
                'has_storage' => isset($_POST['has_storage']) ? 1 : 0,
                'is_furnished' => isset($_POST['is_furnished']) ? 1 : 0,
                'rental_conditions' => $_POST['rental_conditions'] ?? ''
            ];
            
            $_SESSION['offer_step'] = 2;
            header('Location: offer-flat.php');
            exit;
        }
    }
    
    // Step 2: Marketing information
    elseif (isset($_POST['step2'])) {
        // Marketing info is optional, so no validation needed
        $_SESSION['offer_data']['marketing_info'] = [];
        
        // Process marketing information if provided
        if (isset($_POST['marketing_title']) && is_array($_POST['marketing_title'])) {
            for ($i = 0; $i < count($_POST['marketing_title']); $i++) {
                if (!empty($_POST['marketing_title'][$i]) && !empty($_POST['marketing_description'][$i])) {
                    $_SESSION['offer_data']['marketing_info'][] = [
                        'title' => $_POST['marketing_title'][$i],
                        'description' => $_POST['marketing_description'][$i],
                        'url' => $_POST['marketing_url'][$i] ?? ''
                    ];
                }
            }
        }
        
        $_SESSION['offer_step'] = 3;
        header('Location: offer-flat.php');
        exit;
    }
    
    // Step 3: Viewing timetable
    elseif (isset($_POST['step3'])) {
        // Validate inputs
        $errors = [];
        
        // At least one viewing slot is required
        if (!isset($_POST['day']) || !is_array($_POST['day']) || count($_POST['day']) === 0) {
            $errors[] = 'At least one viewing slot is required';
        } else {
            $_SESSION['offer_data']['viewing_slots'] = [];
            
            for ($i = 0; $i < count($_POST['day']); $i++) {
                if (empty($_POST['day'][$i]) || empty($_POST['start_time'][$i]) || empty($_POST['end_time'][$i]) || empty($_POST['contact_number'][$i])) {
                    $errors[] = 'All fields for viewing slot #' . ($i + 1) . ' are required';
                } else {
                    $_SESSION['offer_data']['viewing_slots'][] = [
                        'day' => $_POST['day'][$i],
                        'start_time' => $_POST['start_time'][$i],
                        'end_time' => $_POST['end_time'][$i],
                        'contact_number' => $_POST['contact_number'][$i]
                    ];
                }
            }
        }
        
        // If no errors, proceed to step 4
        if (empty($errors)) {
            $_SESSION['offer_step'] = 4;
            header('Location: offer-flat.php');
            exit;
        }
    }
    
    // Step 4: Confirmation and submission
    elseif (isset($_POST['step4'])) {
        try {
            // Generate unique 6-digit flat reference number
            do {
                $flat_ref = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                
                // Check if reference already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM flats WHERE flat_ref = :flat_ref");
                $stmt->execute(['flat_ref' => $flat_ref]);
            } while ($stmt->fetchColumn() > 0);
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert flat into database
            $stmt = $pdo->prepare("
                INSERT INTO flats (
                    flat_ref, owner_id, location, address, monthly_cost, available_from, available_to,
                    bedrooms, bathrooms, size_sqm, has_heating, has_ac, has_access_control,
                    has_parking, has_backyard, has_playground, has_storage, is_furnished,
                    rental_conditions, status
                ) VALUES (
                    :flat_ref, :owner_id, :location, :address, :monthly_cost, :available_from, :available_to,
                    :bedrooms, :bathrooms, :size_sqm, :has_heating, :has_ac, :has_access_control,
                    :has_parking, :has_backyard, :has_playground, :has_storage, :is_furnished,
                    :rental_conditions, 'pending'
                )
            ");
            
            $stmt->execute([
                'flat_ref' => $flat_ref,
                'owner_id' => $_SESSION['user_id'],
                'location' => $_SESSION['offer_data']['location'],
                'address' => $_SESSION['offer_data']['address'],
                'monthly_cost' => $_SESSION['offer_data']['monthly_cost'],
                'available_from' => $_SESSION['offer_data']['available_from'],
                'available_to' => $_SESSION['offer_data']['available_to'],
                'bedrooms' => $_SESSION['offer_data']['bedrooms'],
                'bathrooms' => $_SESSION['offer_data']['bathrooms'],
                'size_sqm' => $_SESSION['offer_data']['size_sqm'],
                'has_heating' => $_SESSION['offer_data']['has_heating'],
                'has_ac' => $_SESSION['offer_data']['has_ac'],
                'has_access_control' => $_SESSION['offer_data']['has_access_control'],
                'has_parking' => $_SESSION['offer_data']['has_parking'],
                'has_backyard' => $_SESSION['offer_data']['has_backyard'],
                'has_playground' => $_SESSION['offer_data']['has_playground'],
                'has_storage' => $_SESSION['offer_data']['has_storage'],
                'is_furnished' => $_SESSION['offer_data']['is_furnished'],
                'rental_conditions' => $_SESSION['offer_data']['rental_conditions']
            ]);
            
            // Insert marketing information
            if (!empty($_SESSION['offer_data']['marketing_info'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO marketing_info (flat_ref, title, description, url)
                    VALUES (:flat_ref, :title, :description, :url)
                ");
                
                foreach ($_SESSION['offer_data']['marketing_info'] as $info) {
                    $stmt->execute([
                        'flat_ref' => $flat_ref,
                        'title' => $info['title'],
                        'description' => $info['description'],
                        'url' => $info['url']
                    ]);
                }
            }
            
            // Insert viewing slots
            $stmt = $pdo->prepare("
                INSERT INTO viewing_slots (flat_ref, day_of_week, start_time, end_time, contact_number)
                VALUES (:flat_ref, :day_of_week, :start_time, :end_time, :contact_number)
            ");
            
            foreach ($_SESSION['offer_data']['viewing_slots'] as $slot) {
                $stmt->execute([
                    'flat_ref' => $flat_ref,
                    'day_of_week' => $slot['day'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                    'contact_number' => $slot['contact_number']
                ]);
            }
            
            // Create notification for manager
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, title, body, related_entity_type, related_entity_id)
                SELECT :sender_id, user_id, :title, :body, 'flat', :flat_ref
                FROM users
                WHERE user_type = 'manager'
                LIMIT 1
            ");
            
            $stmt->execute([
                'sender_id' => $_SESSION['user_id'],
                'title' => 'New Flat Pending Approval',
                'body' => 'A new flat has been submitted for approval. Flat Reference: ' . $flat_ref,
                'flat_ref' => $flat_ref
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            // Submission successful
            $_SESSION['offer_success'] = true;
            $_SESSION['flat_ref'] = $flat_ref;
            
            // Clean up session data
            unset($_SESSION['offer_data']);
            unset($_SESSION['offer_step']);
            
            header('Location: offer-success.php');
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = 'Submission failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offer Flat for Rent - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <main>
            <section class="offer-section">
                <h1>Offer Your Flat for Rent</h1>
                
                <div class="offer-progress">
                    <div class="progress-step <?php echo $step >= 1 ? 'active' : ''; ?>">
                        <span class="step-number">1</span>
                        <span class="step-label">Flat Details</span>
                    </div>
                    <div class="progress-connector"></div>
                    <div class="progress-step <?php echo $step >= 2 ? 'active' : ''; ?>">
                        <span class="step-number">2</span>
                        <span class="step-label">Marketing Info</span>
                    </div>
                    <div class="progress-connector"></div>
                    <div class="progress-step <?php echo $step >= 3 ? 'active' : ''; ?>">
                        <span class="step-number">3</span>
                        <span class="step-label">Viewing Schedule</span>
                    </div>
                    <div class="progress-connector"></div>
                    <div class="progress-step <?php echo $step >= 4 ? 'active' : ''; ?>">
                        <span class="step-number">4</span>
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
                    <!-- Step 1: Flat Details -->
                    <form action="offer-flat.php" method="POST" class="offer-form">
                        <div class="form-group">
                            <label for="location">Location <span class="required">*</span></label>
                            <input type="text" id="location" name="location" required value="<?php echo isset($_SESSION['offer_data']['location']) ? htmlspecialchars($_SESSION['offer_data']['location']) : ''; ?>">
                            <small>City or area where the flat is located (e.g., Ramallah, Birzeit)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Full Address <span class="required">*</span></label>
                            <textarea id="address" name="address" required rows="2"><?php echo isset($_SESSION['offer_data']['address']) ? htmlspecialchars($_SESSION['offer_data']['address']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="monthly_cost">Monthly Rent ($) <span class="required">*</span></label>
                                <input type="number" id="monthly_cost" name="monthly_cost" required min="1" step="0.01" value="<?php echo isset($_SESSION['offer_data']['monthly_cost']) ? htmlspecialchars($_SESSION['offer_data']['monthly_cost']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="available_from">Available From <span class="required">*</span></label>
                                <input type="date" id="available_from" name="available_from" required value="<?php echo isset($_SESSION['offer_data']['available_from']) ? htmlspecialchars($_SESSION['offer_data']['available_from']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="available_to">Available To <span class="required">*</span></label>
                                <input type="date" id="available_to" name="available_to" required value="<?php echo isset($_SESSION['offer_data']['available_to']) ? htmlspecialchars($_SESSION['offer_data']['available_to']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="bedrooms">Bedrooms <span class="required">*</span></label>
                                <input type="number" id="bedrooms" name="bedrooms" required min="1" value="<?php echo isset($_SESSION['offer_data']['bedrooms']) ? htmlspecialchars($_SESSION['offer_data']['bedrooms']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="bathrooms">Bathrooms <span class="required">*</span></label>
                                <input type="number" id="bathrooms" name="bathrooms" required min="1" value="<?php echo isset($_SESSION['offer_data']['bathrooms']) ? htmlspecialchars($_SESSION['offer_data']['bathrooms']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="size_sqm">Size (m²) <span class="required">*</span></label>
                                <input type="number" id="size_sqm" name="size_sqm" required min="1" step="0.01" value="<?php echo isset($_SESSION['offer_data']['size_sqm']) ? htmlspecialchars($_SESSION['offer_data']['size_sqm']) : ''; ?>">
                            </div>
                        </div>
                        
                        <fieldset class="form-group">
                            <legend>Features</legend>
                            
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" id="has_heating" name="has_heating" <?php echo isset($_SESSION['offer_data']['has_heating']) && $_SESSION['offer_data']['has_heating'] ? 'checked' : ''; ?>>
                                    <label for="has_heating">Heating System</label>
                                </div>
                                
                                <div class="checkbox-item">
                                    <input type="checkbox" id="has_ac" name="has_ac" <?php echo isset($_SESSION['offer_data']['has_ac']) && $_SESSION['offer_data']['has_ac'] ? 'checked' : ''; ?>>
                                    <label for="has_ac">Air Conditioning</label>
                                </div>
                                
                                <div class="checkbox-item">
                                    <input type="checkbox" id="has_access_control" name="has_access_control" <?php echo isset($_SESSION['offer_data']['has_access_control']) && $_SESSION['offer_data']['has_access_control'] ? 'checked' : ''; ?>>
                                    <label for="has_access_control">Access Control</label>
                                </div>
                                
                                <div class="checkbox-item">
                                    <input type="checkbox" id="has_parking" name="has_parking" <?php echo isset($_SESSION['offer_data']['has_parking']) && $_SESSION['offer_data']['has_parking'] ? 'checked' : ''; ?>>
                                    <label for="has_parking">Parking</label>
                                </div>
                                
                                <div class="checkbox-item">
                                    <input type="checkbox" id="has_playground" name="has_playground" <?php echo isset($_SESSION['offer_data']['has_playground']) && $_SESSION['offer_data']['has_playground'] ? 'checked' : ''; ?>>
                                    <label for="has_playground">Playground</label>
                                </div>
                                
                                <div class="checkbox-item">
                                    <input type="checkbox" id="has_storage" name="has_storage" <?php echo isset($_SESSION['offer_data']['has_storage']) && $_SESSION['offer_data']['has_storage'] ? 'checked' : ''; ?>>
                                    <label for="has_storage">Storage</label>
                                </div>
                                
                                <div class="checkbox-item">
                                    <input type="checkbox" id="is_furnished" name="is_furnished" <?php echo isset($_SESSION['offer_data']['is_furnished']) && $_SESSION['offer_data']['is_furnished'] ? 'checked' : ''; ?>>
                                    <label for="is_furnished">Furnished</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="has_backyard">Backyard</label>
                                <select id="has_backyard" name="has_backyard">
                                    <option value="none" <?php echo (!isset($_SESSION['offer_data']['has_backyard']) || $_SESSION['offer_data']['has_backyard'] === 'none') ? 'selected' : ''; ?>>None</option>
                                    <option value="individual" <?php echo (isset($_SESSION['offer_data']['has_backyard']) && $_SESSION['offer_data']['has_backyard'] === 'individual') ? 'selected' : ''; ?>>Individual</option>
                                    <option value="shared" <?php echo (isset($_SESSION['offer_data']['has_backyard']) && $_SESSION['offer_data']['has_backyard'] === 'shared') ? 'selected' : ''; ?>>Shared</option>
                                </select>
                            </div>
                        </fieldset>
                        
                        <div class="form-group">
                            <label for="rental_conditions">Rental Conditions</label>
                            <textarea id="rental_conditions" name="rental_conditions" rows="4"><?php echo isset($_SESSION['offer_data']['rental_conditions']) ? htmlspecialchars($_SESSION['offer_data']['rental_conditions']) : ''; ?></textarea>
                            <small>Specify any special conditions or rules for renting this flat</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="step1" class="btn btn-primary">Next Step</button>
                        </div>
                    </form>
                
                <?php elseif ($step === 2): ?>
                    <!-- Step 2: Marketing Information -->
                    <form action="offer-flat.php" method="POST" class="offer-form">
                        <p class="form-info">Add information about nearby places and amenities to help market your flat (optional).</p>
                        
                        <div id="marketing-container">
                            <?php
                            $marketing_info = isset($_SESSION['offer_data']['marketing_info']) ? $_SESSION['offer_data']['marketing_info'] : [];
                            
                            if (empty($marketing_info)) {
                                // Add one empty form by default
                                ?>
                                <div class="marketing-item">
                                    <div class="form-group">
                                        <label for="marketing_title_0">Title</label>
                                        <input type="text" id="marketing_title_0" name="marketing_title[]" placeholder="e.g., Nearby School">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="marketing_description_0">Description</label>
                                        <textarea id="marketing_description_0" name="marketing_description[]" rows="2" placeholder="Brief description of the place"></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="marketing_url_0">URL (optional)</label>
                                        <input type="url" id="marketing_url_0" name="marketing_url[]" placeholder="https://example.com">
                                    </div>
                                </div>
                                <?php
                            } else {
                                // Display existing marketing info
                                foreach ($marketing_info as $index => $info) {
                                    ?>
                                    <div class="marketing-item">
                                        <div class="form-group">
                                            <label for="marketing_title_<?php echo $index; ?>">Title</label>
                                            <input type="text" id="marketing_title_<?php echo $index; ?>" name="marketing_title[]" value="<?php echo htmlspecialchars($info['title']); ?>">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="marketing_description_<?php echo $index; ?>">Description</label>
                                            <textarea id="marketing_description_<?php echo $index; ?>" name="marketing_description[]" rows="2"><?php echo htmlspecialchars($info['description']); ?></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="marketing_url_<?php echo $index; ?>">URL (optional)</label>
                                            <input type="url" id="marketing_url_<?php echo $index; ?>" name="marketing_url[]" value="<?php echo htmlspecialchars($info['url']); ?>">
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" id="add-marketing" class="btn btn-secondary">Add Another Place</button>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" onclick="window.location.href='offer-flat.php?step=1'" class="btn btn-secondary">Previous Step</button>
                            <button type="submit" name="step2" class="btn btn-primary">Next Step</button>
                        </div>
                    </form>
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const container = document.getElementById('marketing-container');
                            const addButton = document.getElementById('add-marketing');
                            
                            addButton.addEventListener('click', function() {
                                const items = container.querySelectorAll('.marketing-item');
                                const index = items.length;
                                
                                const newItem = document.createElement('div');
                                newItem.className = 'marketing-item';
                                
                                newItem.innerHTML = `
                                    <div class="form-group">
                                        <label for="marketing_title_${index}">Title</label>
                                        <input type="text" id="marketing_title_${index}" name="marketing_title[]" placeholder="e.g., Nearby School">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="marketing_description_${index}">Description</label>
                                        <textarea id="marketing_description_${index}" name="marketing_description[]" rows="2" placeholder="Brief description of the place"></textarea>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="marketing_url_${index}">URL (optional)</label>
                                        <input type="url" id="marketing_url_${index}" name="marketing_url[]" placeholder="https://example.com">
                                    </div>
                                    
                                    <button type="button" class="btn btn-small btn-danger remove-marketing">Remove</button>
                                `;
                                
                                container.appendChild(newItem);
                                
                                // Add event listener to the remove button
                                newItem.querySelector('.remove-marketing').addEventListener('click', function() {
                                    container.removeChild(newItem);
                                });
                            });
                        });
                    </script>
                
                <?php elseif ($step === 3): ?>
                    <!-- Step 3: Viewing Timetable -->
                    <form action="offer-flat.php" method="POST" class="offer-form">
                        <p class="form-info">Provide available times for potential customers to view the flat.</p>
                        
                        <div id="viewing-container">
                            <?php
                            $viewing_slots = isset($_SESSION['offer_data']['viewing_slots']) ? $_SESSION['offer_data']['viewing_slots'] : [];
                            
                            if (empty($viewing_slots)) {
                                // Add one empty form by default
                                ?>
                                <div class="viewing-item">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="day_0">Day <span class="required">*</span></label>
                                            <select id="day_0" name="day[]" required>
                                                <option value="">Select Day</option>
                                                <option value="Monday">Monday</option>
                                                <option value="Tuesday">Tuesday</option>
                                                <option value="Wednesday">Wednesday</option>
                                                <option value="Thursday">Thursday</option>
                                                <option value="Friday">Friday</option>
                                                <option value="Saturday">Saturday</option>
                                                <option value="Sunday">Sunday</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="start_time_0">Start Time <span class="required">*</span></label>
                                            <input type="time" id="start_time_0" name="start_time[]" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="end_time_0">End Time <span class="required">*</span></label>
                                            <input type="time" id="end_time_0" name="end_time[]" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="contact_number_0">Contact Number <span class="required">*</span></label>
                                        <input type="tel" id="contact_number_0" name="contact_number[]" required>
                                    </div>
                                </div>
                                <?php
                            } else {
                                // Display existing viewing slots
                                foreach ($viewing_slots as $index => $slot) {
                                    ?>
                                    <div class="viewing-item">
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="day_<?php echo $index; ?>">Day <span class="required">*</span></label>
                                                <select id="day_<?php echo $index; ?>" name="day[]" required>
                                                    <option value="">Select Day</option>
                                                    <option value="Monday" <?php echo $slot['day'] === 'Monday' ? 'selected' : ''; ?>>Monday</option>
                                                    <option value="Tuesday" <?php echo $slot['day'] === 'Tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                                    <option value="Wednesday" <?php echo $slot['day'] === 'Wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                                    <option value="Thursday" <?php echo $slot['day'] === 'Thursday' ? 'selected' : ''; ?>>Thursday</option>
                                                    <option value="Friday" <?php echo $slot['day'] === 'Friday' ? 'selected' : ''; ?>>Friday</option>
                                                    <option value="Saturday" <?php echo $slot['day'] === 'Saturday' ? 'selected' : ''; ?>>Saturday</option>
                                                    <option value="Sunday" <?php echo $slot['day'] === 'Sunday' ? 'selected' : ''; ?>>Sunday</option>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="start_time_<?php echo $index; ?>">Start Time <span class="required">*</span></label>
                                                <input type="time" id="start_time_<?php echo $index; ?>" name="start_time[]" required value="<?php echo htmlspecialchars($slot['start_time']); ?>">
                                            </div>
                                            
                                            <div class="form-group">
                                                <label for="end_time_<?php echo $index; ?>">End Time <span class="required">*</span></label>
                                                <input type="time" id="end_time_<?php echo $index; ?>" name="end_time[]" required value="<?php echo htmlspecialchars($slot['end_time']); ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="contact_number_<?php echo $index; ?>">Contact Number <span class="required">*</span></label>
                                            <input type="tel" id="contact_number_<?php echo $index; ?>" name="contact_number[]" required value="<?php echo htmlspecialchars($slot['contact_number']); ?>">
                                        </div>
                                        
                                        <?php if ($index > 0): ?>
                                            <button type="button" class="btn btn-small btn-danger remove-viewing">Remove</button>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" id="add-viewing" class="btn btn-secondary">Add Another Time Slot</button>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" onclick="window.location.href='offer-flat.php?step=2'" class="btn btn-secondary">Previous Step</button>
                            <button type="submit" name="step3" class="btn btn-primary">Next Step</button>
                        </div>
                    </form>
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const container = document.getElementById('viewing-container');
                            const addButton = document.getElementById('add-viewing');
                            
                            // Add event listeners to existing remove buttons
                            document.querySelectorAll('.remove-viewing').forEach(button => {
                                button.addEventListener('click', function() {
                                    container.removeChild(this.parentElement);
                                });
                            });
                            
                            addButton.addEventListener('click', function() {
                                const items = container.querySelectorAll('.viewing-item');
                                const index = items.length;
                                
                                const newItem = document.createElement('div');
                                newItem.className = 'viewing-item';
                                
                                newItem.innerHTML = `
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="day_${index}">Day <span class="required">*</span></label>
                                            <select id="day_${index}" name="day[]" required>
                                                <option value="">Select Day</option>
                                                <option value="Monday">Monday</option>
                                                <option value="Tuesday">Tuesday</option>
                                                <option value="Wednesday">Wednesday</option>
                                                <option value="Thursday">Thursday</option>
                                                <option value="Friday">Friday</option>
                                                <option value="Saturday">Saturday</option>
                                                <option value="Sunday">Sunday</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="start_time_${index}">Start Time <span class="required">*</span></label>
                                            <input type="time" id="start_time_${index}" name="start_time[]" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="end_time_${index}">End Time <span class="required">*</span></label>
                                            <input type="time" id="end_time_${index}" name="end_time[]" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="contact_number_${index}">Contact Number <span class="required">*</span></label>
                                        <input type="tel" id="contact_number_${index}" name="contact_number[]" required>
                                    </div>
                                    
                                    <button type="button" class="btn btn-small btn-danger remove-viewing">Remove</button>
                                `;
                                
                                container.appendChild(newItem);
                                
                                // Add event listener to the remove button
                                newItem.querySelector('.remove-viewing').addEventListener('click', function() {
                                    container.removeChild(newItem);
                                });
                            });
                        });
                    </script>
                
                <?php elseif ($step === 4): ?>
                    <!-- Step 4: Confirmation -->
                    <form action="offer-flat.php" method="POST" class="offer-form">
                        <h2>Review Your Flat Details</h2>
                        
                        <div class="review-details">
                            <div class="review-group">
                                <h3>Basic Information</h3>
                                <p><strong>Location:</strong> <?php echo htmlspecialchars($_SESSION['offer_data']['location']); ?></p>
                                <p><strong>Address:</strong> <?php echo htmlspecialchars($_SESSION['offer_data']['address']); ?></p>
                                <p><strong>Monthly Rent:</strong> $<?php echo number_format($_SESSION['offer_data']['monthly_cost'], 2); ?></p>
                                <p><strong>Available From:</strong> <?php echo htmlspecialchars($_SESSION['offer_data']['available_from']); ?></p>
                                <p><strong>Available To:</strong> <?php echo htmlspecialchars($_SESSION['offer_data']['available_to']); ?></p>
                            </div>
                            
                            <div class="review-group">
                                <h3>Property Details</h3>
                                <p><strong>Bedrooms:</strong> <?php echo htmlspecialchars($_SESSION['offer_data']['bedrooms']); ?></p>
                                <p><strong>Bathrooms:</strong> <?php echo htmlspecialchars($_SESSION['offer_data']['bathrooms']); ?></p>
                                <p><strong>Size:</strong> <?php echo htmlspecialchars($_SESSION['offer_data']['size_sqm']); ?> m²</p>
                                <p><strong>Furnished:</strong> <?php echo $_SESSION['offer_data']['is_furnished'] ? 'Yes' : 'No'; ?></p>
                            </div>
                            
                            <div class="review-group">
                                <h3>Features</h3>
                                <ul>
                                    <?php if ($_SESSION['offer_data']['has_heating']): ?><li>Heating System</li><?php endif; ?>
                                    <?php if ($_SESSION['offer_data']['has_ac']): ?><li>Air Conditioning</li><?php endif; ?>
                                    <?php if ($_SESSION['offer_data']['has_access_control']): ?><li>Access Control</li><?php endif; ?>
                                    <?php if ($_SESSION['offer_data']['has_parking']): ?><li>Parking</li><?php endif; ?>
                                    <?php if ($_SESSION['offer_data']['has_backyard'] !== 'none'): ?>
                                        <li>Backyard (<?php echo $_SESSION['offer_data']['has_backyard']; ?>)</li>
                                    <?php endif; ?>
                                    <?php if ($_SESSION['offer_data']['has_playground']): ?><li>Playground</li><?php endif; ?>
                                    <?php if ($_SESSION['offer_data']['has_storage']): ?><li>Storage</li><?php endif; ?>
                                </ul>
                                
                                <?php if (!empty($_SESSION['offer_data']['rental_conditions'])): ?>
                                    <p><strong>Rental Conditions:</strong></p>
                                    <p><?php echo nl2br(htmlspecialchars($_SESSION['offer_data']['rental_conditions'])); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($_SESSION['offer_data']['marketing_info'])): ?>
                                <div class="review-group">
                                    <h3>Marketing Information</h3>
                                    <ul>
                                        <?php foreach ($_SESSION['offer_data']['marketing_info'] as $info): ?>
                                            <li>
                                                <strong><?php echo htmlspecialchars($info['title']); ?>:</strong>
                                                <?php echo htmlspecialchars($info['description']); ?>
                                                <?php if (!empty($info['url'])): ?>
                                                    <br><a href="<?php echo htmlspecialchars($info['url']); ?>" target="_blank">More Info</a>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <div class="review-group">
                                <h3>Viewing Schedule</h3>
                                <ul>
                                    <?php foreach ($_SESSION['offer_data']['viewing_slots'] as $slot): ?>
                                        <li>
                                            <strong><?php echo htmlspecialchars($slot['day']); ?>:</strong>
                                            <?php echo htmlspecialchars($slot['start_time']); ?> - <?php echo htmlspecialchars($slot['end_time']); ?>
                                            <br>Contact: <?php echo htmlspecialchars($slot['contact_number']); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="form-info">
                            <p>By submitting this form, your flat will be sent to the manager for approval. Once approved, it will be listed on the website for customers to rent.</p>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" onclick="window.location.href='offer-flat.php?step=3'" class="btn btn-secondary">Previous Step</button>
                            <button type="submit" name="step4" class="btn btn-primary">Submit Flat</button>
                        </div>
                    </form>
                <?php endif; ?>
            </section>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
