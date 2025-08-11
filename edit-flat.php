<?php
session_start();

// Check if user is logged in as an owner
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'owner') {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Check if flat reference is provided
if (!isset($_GET['ref'])) {
    header('Location: my-flats.php');
    exit;
}

$flat_ref = $_GET['ref'];

require_once 'database.inc.php';

// Check if flat exists and belongs to the current owner
$stmt = $pdo->prepare("
    SELECT * FROM flats 
    WHERE flat_ref = :flat_ref AND owner_id = :owner_id AND status = 'approved'
");
$stmt->execute(['flat_ref' => $flat_ref, 'owner_id' => $_SESSION['user_id']]);

if ($stmt->rowCount() === 0) {
    header('Location: my-flats.php');
    exit;
}

$flat = $stmt->fetch();

// Get marketing information
$stmt = $pdo->prepare("SELECT * FROM marketing_info WHERE flat_ref = :flat_ref");
$stmt->execute(['flat_ref' => $flat_ref]);
$marketing_info = $stmt->fetchAll();

// Get viewing slots
$stmt = $pdo->prepare("SELECT * FROM viewing_slots WHERE flat_ref = :flat_ref");
$stmt->execute(['flat_ref' => $flat_ref]);
$viewing_slots = $stmt->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    // If no errors, update flat details
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update flat details
            $stmt = $pdo->prepare("
                UPDATE flats SET
                    location = :location,
                    address = :address,
                    monthly_cost = :monthly_cost,
                    available_from = :available_from,
                    available_to = :available_to,
                    has_heating = :has_heating,
                    has_ac = :has_ac,
                    has_access_control = :has_access_control,
                    has_parking = :has_parking,
                    has_backyard = :has_backyard,
                    has_playground = :has_playground,
                    has_storage = :has_storage,
                    is_furnished = :is_furnished,
                    rental_conditions = :rental_conditions
                WHERE flat_ref = :flat_ref AND owner_id = :owner_id
            ");
            
            $stmt->execute([
                'location' => $_POST['location'],
                'address' => $_POST['address'],
                'monthly_cost' => $_POST['monthly_cost'],
                'available_from' => $_POST['available_from'],
                'available_to' => $_POST['available_to'],
                'has_heating' => isset($_POST['has_heating']) ? 1 : 0,
                'has_ac' => isset($_POST['has_ac']) ? 1 : 0,
                'has_access_control' => isset($_POST['has_access_control']) ? 1 : 0,
                'has_parking' => isset($_POST['has_parking']) ? 1 : 0,
                'has_backyard' => $_POST['has_backyard'] ?? 'none',
                'has_playground' => isset($_POST['has_playground']) ? 1 : 0,
                'has_storage' => isset($_POST['has_storage']) ? 1 : 0,
                'is_furnished' => isset($_POST['is_furnished']) ? 1 : 0,
                'rental_conditions' => $_POST['rental_conditions'] ?? '',
                'flat_ref' => $flat_ref,
                'owner_id' => $_SESSION['user_id']
            ]);
            
            // Delete existing marketing information
            $stmt = $pdo->prepare("DELETE FROM marketing_info WHERE flat_ref = :flat_ref");
            $stmt->execute(['flat_ref' => $flat_ref]);
            
            // Insert new marketing information
            if (isset($_POST['marketing_title']) && is_array($_POST['marketing_title'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO marketing_info (flat_ref, title, description, url)
                    VALUES (:flat_ref, :title, :description, :url)
                ");
                
                for ($i = 0; $i < count($_POST['marketing_title']); $i++) {
                    if (!empty($_POST['marketing_title'][$i]) && !empty($_POST['marketing_description'][$i])) {
                        $stmt->execute([
                            'flat_ref' => $flat_ref,
                            'title' => $_POST['marketing_title'][$i],
                            'description' => $_POST['marketing_description'][$i],
                            'url' => $_POST['marketing_url'][$i] ?? ''
                        ]);
                    }
                }
            }
            
            // Delete existing viewing slots
            $stmt = $pdo->prepare("DELETE FROM viewing_slots WHERE flat_ref = :flat_ref");
            $stmt->execute(['flat_ref' => $flat_ref]);
            
            // Insert new viewing slots
            if (isset($_POST['day']) && is_array($_POST['day'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO viewing_slots (flat_ref, day_of_week, start_time, end_time, contact_number)
                    VALUES (:flat_ref, :day_of_week, :start_time, :end_time, :contact_number)
                ");
                
                for ($i = 0; $i < count($_POST['day']); $i++) {
                    if (!empty($_POST['day'][$i]) && !empty($_POST['start_time'][$i]) && !empty($_POST['end_time'][$i]) && !empty($_POST['contact_number'][$i])) {
                        $stmt->execute([
                            'flat_ref' => $flat_ref,
                            'day_of_week' => $_POST['day'][$i],
                            'start_time' => $_POST['start_time'][$i],
                            'end_time' => $_POST['end_time'][$i],
                            'contact_number' => $_POST['contact_number'][$i]
                        ]);
                    }
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect to flat details page
            header('Location: flat-detail.php?ref=' . $flat_ref . '&updated=1');
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
    <title>Edit Flat - <?php echo $flat['flat_ref']; ?> - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <main>
            <section class="edit-flat-section">
                <h1>Edit Flat - Ref: <?php echo $flat['flat_ref']; ?></h1>
                
                <?php if (isset($errors) && !empty($errors)): ?>
                    <div class="error-messages">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form action="edit-flat.php?ref=<?php echo $flat_ref; ?>" method="POST" class="edit-flat-form">
                    <div class="form-group">
                        <label for="location">Location <span class="required">*</span></label>
                        <input type="text" id="location" name="location" required value="<?php echo htmlspecialchars($flat['location']); ?>">
                        <small>City or area where the flat is located (e.g., Ramallah, Birzeit)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Full Address <span class="required">*</span></label>
                        <textarea id="address" name="address" required rows="2"><?php echo htmlspecialchars($flat['address']); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="monthly_cost">Monthly Rent ($) <span class="required">*</span></label>
                            <input type="number" id="monthly_cost" name="monthly_cost" required min="1" step="0.01" value="<?php echo htmlspecialchars($flat['monthly_cost']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="available_from">Available From <span class="required">*</span></label>
                            <input type="date" id="available_from" name="available_from" required value="<?php echo htmlspecialchars($flat['available_from']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="available_to">Available To <span class="required">*</span></label>
                            <input type="date" id="available_to" name="available_to" required value="<?php echo htmlspecialchars($flat['available_to']); ?>">
                        </div>
                    </div>
                    
                    <fieldset class="form-group">
                        <legend>Features</legend>
                        
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="has_heating" name="has_heating" <?php echo $flat['has_heating'] ? 'checked' : ''; ?>>
                                <label for="has_heating">Heating System</label>
                            </div>
                            
                            <div class="checkbox-item">
                                <input type="checkbox" id="has_ac" name="has_ac" <?php echo $flat['has_ac'] ? 'checked' : ''; ?>>
                                <label for="has_ac">Air Conditioning</label>
                            </div>
                            
                            <div class="checkbox-item">
                                <input type="checkbox" id="has_access_control" name="has_access_control" <?php echo $flat['has_access_control'] ? 'checked' : ''; ?>>
                                <label for="has_access_control">Access Control</label>
                            </div>
                            
                            <div class="checkbox-item">
                                <input type="checkbox" id="has_parking" name="has_parking" <?php echo $flat['has_parking'] ? 'checked' : ''; ?>>
                                <label for="has_parking">Parking</label>
                            </div>
                            
                            <div class="checkbox-item">
                                <input type="checkbox" id="has_playground" name="has_playground" <?php echo $flat['has_playground'] ? 'checked' : ''; ?>>
                                <label for="has_playground">Playground</label>
                            </div>
                            
                            <div class="checkbox-item">
                                <input type="checkbox" id="has_storage" name="has_storage" <?php echo $flat['has_storage'] ? 'checked' : ''; ?>>
                                <label for="has_storage">Storage</label>
                            </div>
                            
                            <div class="checkbox-item">
                                <input type="checkbox" id="is_furnished" name="is_furnished" <?php echo $flat['is_furnished'] ? 'checked' : ''; ?>>
                                <label for="is_furnished">Furnished</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="has_backyard">Backyard</label>
                            <select id="has_backyard" name="has_backyard">
                                <option value="none" <?php echo $flat['has_backyard'] === 'none' ? 'selected' : ''; ?>>None</option>
                                <option value="individual" <?php echo $flat['has_backyard'] === 'individual' ? 'selected' : ''; ?>>Individual</option>
                                <option value="shared" <?php echo $flat['has_backyard'] === 'shared' ? 'selected' : ''; ?>>Shared</option>
                            </select>
                        </div>
                    </fieldset>
                    
                    <div class="form-group">
                        <label for="rental_conditions">Rental Conditions</label>
                        <textarea id="rental_conditions" name="rental_conditions" rows="4"><?php echo htmlspecialchars($flat['rental_conditions']); ?></textarea>
                        <small>Specify any special conditions or rules for renting this flat</small>
                    </div>
                    
                    <h2>Marketing Information</h2>
                    <p class="form-info">Add information about nearby places and amenities to help market your flat (optional).</p>
                    
                    <div id="marketing-container">
                        <?php if (count($marketing_info) > 0): ?>
                            <?php foreach ($marketing_info as $index => $info): ?>
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
                                    
                                    <?php if ($index > 0): ?>
                                        <button type="button" class="btn btn-small btn-danger remove-marketing">Remove</button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
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
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" id="add-marketing" class="btn btn-secondary">Add Another Place</button>
                    </div>
                    
                    <h2>Viewing Schedule</h2>
                    <p class="form-info">Provide available times for potential customers to view the flat.</p>
                    
                    <div id="viewing-container">
                        <?php if (count($viewing_slots) > 0): ?>
                            <?php foreach ($viewing_slots as $index => $slot): ?>
                                <div class="viewing-item">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="day_<?php echo $index; ?>">Day <span class="required">*</span></label>
                                            <select id="day_<?php echo $index; ?>" name="day[]" required>
                                                <option value="">Select Day</option>
                                                <option value="Monday" <?php echo $slot['day_of_week'] === 'Monday' ? 'selected' : ''; ?>>Monday</option>
                                                <option value="Tuesday" <?php echo $slot['day_of_week'] === 'Tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                                <option value="Wednesday" <?php echo $slot['day_of_week'] === 'Wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                                <option value="Thursday" <?php echo $slot['day_of_week'] === 'Thursday' ? 'selected' : ''; ?>>Thursday</option>
                                                <option value="Friday" <?php echo $slot['day_of_week'] === 'Friday' ? 'selected' : ''; ?>>Friday</option>
                                                <option value="Saturday" <?php echo $slot['day_of_week'] === 'Saturday' ? 'selected' : ''; ?>>Saturday</option>
                                                <option value="Sunday" <?php echo $slot['day_of_week'] === 'Sunday' ? 'selected' : ''; ?>>Sunday</option>
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
                            <?php endforeach; ?>
                        <?php else: ?>
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
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" id="add-viewing" class="btn btn-secondary">Add Another Time Slot</button>
                    </div>
                    
                    <div class="form-actions">
                        <a href="flat-detail.php?ref=<?php echo $flat_ref; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Marketing information
            const marketingContainer = document.getElementById('marketing-container');
            const addMarketingButton = document.getElementById('add-marketing');
            
            // Add event listeners to existing remove buttons
            document.querySelectorAll('.remove-marketing').forEach(button => {
                button.addEventListener('click', function() {
                    marketingContainer.removeChild(this.parentElement);
                });
            });
            
            addMarketingButton.addEventListener('click', function() {
                const items = marketingContainer.querySelectorAll('.marketing-item');
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
                
                marketingContainer.appendChild(newItem);
                
                // Add event listener to the remove button
                newItem.querySelector('.remove-marketing').addEventListener('click', function() {
                    marketingContainer.removeChild(newItem);
                });
            });
            
            // Viewing slots
            const viewingContainer = document.getElementById('viewing-container');
            const addViewingButton = document.getElementById('add-viewing');
            
            // Add event listeners to existing remove buttons
            document.querySelectorAll('.remove-viewing').forEach(button => {
                button.addEventListener('click', function() {
                    viewingContainer.removeChild(this.parentElement);
                });
            });
            
            addViewingButton.addEventListener('click', function() {
                const items = viewingContainer.querySelectorAll('.viewing-item');
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
                
                viewingContainer.appendChild(newItem);
                
                // Add event listener to the remove button
                newItem.querySelector('.remove-viewing').addEventListener('click', function() {
                    viewingContainer.removeChild(newItem);
                });
            });
        });
    </script>
</body>
</html>
