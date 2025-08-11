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
    $_SESSION['appointment_flat_ref'] = $flat_ref; // Save flat reference for after login
    header('Location: login.php?redirect=' . urlencode('request-appointment.php?ref=' . $flat_ref));
    exit;
}

// Get flat details
$stmt = $pdo->prepare("
    SELECT f.*, u.name as owner_name, u.user_id as owner_id
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

// Get available viewing slots
$stmt = $pdo->prepare("
    SELECT vs.slot_id, vs.day_of_week, vs.start_time, vs.end_time, vs.contact_number,
           a.appointment_id, a.appointment_date, a.status
    FROM viewing_slots vs
    LEFT JOIN appointments a ON vs.slot_id = a.slot_id AND a.appointment_date >= CURDATE()
    WHERE vs.flat_ref = :flat_ref
    ORDER BY 
        CASE 
            WHEN vs.day_of_week = 'Monday' THEN 1
            WHEN vs.day_of_week = 'Tuesday' THEN 2
            WHEN vs.day_of_week = 'Wednesday' THEN 3
            WHEN vs.day_of_week = 'Thursday' THEN 4
            WHEN vs.day_of_week = 'Friday' THEN 5
            WHEN vs.day_of_week = 'Saturday' THEN 6
            WHEN vs.day_of_week = 'Sunday' THEN 7
        END,
        vs.start_time
");
$stmt->execute(['flat_ref' => $flat_ref]);
$slots = $stmt->fetchAll();

// Process appointment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_slot'])) {
    $slot_id = $_POST['slot_id'];
    $appointment_date = $_POST['appointment_date'];
    $errors = [];
    
    // Validate inputs
    if (empty($slot_id) || empty($appointment_date)) {
        $errors[] = 'Slot ID and appointment date are required';
    }
    
    // Check if slot exists
    $stmt = $pdo->prepare("SELECT * FROM viewing_slots WHERE slot_id = :slot_id AND flat_ref = :flat_ref");
    $stmt->execute(['slot_id' => $slot_id, 'flat_ref' => $flat_ref]);
    if ($stmt->rowCount() === 0) {
        $errors[] = 'Invalid viewing slot';
    }
    
    // Check if slot is already booked for the selected date
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM appointments
        WHERE slot_id = :slot_id AND appointment_date = :appointment_date
        AND status IN ('pending', 'accepted')
    ");
    $stmt->execute(['slot_id' => $slot_id, 'appointment_date' => $appointment_date]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'This slot is already booked for the selected date';
    }
    
    // Check if appointment date is valid (not in the past and matches day of week)
    $appointment_day = date('l', strtotime($appointment_date));
    $stmt = $pdo->prepare("SELECT day_of_week FROM viewing_slots WHERE slot_id = :slot_id");
    $stmt->execute(['slot_id' => $slot_id]);
    $slot_day = $stmt->fetchColumn();
    
    if (strtotime($appointment_date) < strtotime('today')) {
        $errors[] = 'Appointment date cannot be in the past';
    } elseif ($appointment_day !== $slot_day) {
        $errors[] = 'Appointment date must be a ' . $slot_day;
    }
    
    // If no errors, book the appointment
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert appointment
            $stmt = $pdo->prepare("
                INSERT INTO appointments (slot_id, customer_id, appointment_date, status)
                VALUES (:slot_id, :customer_id, :appointment_date, 'pending')
            ");
            
            $stmt->execute([
                'slot_id' => $slot_id,
                'customer_id' => $_SESSION['user_id'],
                'appointment_date' => $appointment_date
            ]);
            
            $appointment_id = $pdo->lastInsertId();
            
            // Create notification for owner
            $stmt = $pdo->prepare("
                INSERT INTO messages (
                    sender_id, receiver_id, title, body, related_entity_type, related_entity_id
                ) VALUES (
                    :sender_id, :receiver_id, :title, :body, 'appointment', :appointment_id
                )
            ");
            
            $stmt->execute([
                'sender_id' => $_SESSION['user_id'],
                'receiver_id' => $flat['owner_id'],
                'title' => 'New Viewing Appointment Request',
                'body' => 'A customer has requested to view your flat (Ref: ' . $flat_ref . ') on ' . date('d/m/Y', strtotime($appointment_date)) . '.',
                'appointment_id' => $appointment_id
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            // Appointment request successful
            $_SESSION['appointment_success'] = true;
            
            header('Location: appointment-success.php');
            exit;
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = 'Appointment booking failed: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Viewing Appointment - <?php echo $flat['flat_ref']; ?> - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <main>
            <section class="appointment-section">
                <h1>Request Viewing Appointment</h1>
                
                <div class="flat-summary">
                    <h2>Flat Details - Ref: <?php echo $flat['flat_ref']; ?></h2>
                    <div class="summary-details">
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($flat['location']); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($flat['address']); ?></p>
                        <p><strong>Owner:</strong> <?php echo htmlspecialchars($flat['owner_name']); ?></p>
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
                
                <div class="available-slots">
                    <h2>Available Viewing Slots</h2>
                    
                    <?php if (count($slots) > 0): ?>
                        <table class="slots-table">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Contact Number</th>
                                    <th>Available Dates</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($slots as $index => $slot): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'even-row' : 'odd-row'; ?>">
                                        <td><?php echo htmlspecialchars($slot['day_of_week']); ?></td>
                                        <td><?php echo date('H:i', strtotime($slot['start_time'])); ?> - <?php echo date('H:i', strtotime($slot['end_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($slot['contact_number']); ?></td>
                                        <td>
                                            <form action="request-appointment.php?ref=<?php echo $flat_ref; ?>" method="POST" class="date-selection-form">
                                                <input type="hidden" name="slot_id" value="<?php echo $slot['slot_id']; ?>">
                                                
                                                <?php
                                                // Generate next 4 weeks of dates for this day
                                                $dates = [];
                                                $current_date = new DateTime();
                                                $day_of_week = $slot['day_of_week'];
                                                
                                                // Find the next occurrence of this day
                                                while ($current_date->format('l') !== $day_of_week) {
                                                    $current_date->modify('+1 day');
                                                }
                                                
                                                // Generate 4 weeks of dates
                                                for ($i = 0; $i < 4; $i++) {
                                                    $date_str = $current_date->format('Y-m-d');
                                                    $display_date = $current_date->format('d/m/Y');
                                                    
                                                    // Check if this date is already booked
                                                    $is_booked = false;
                                                    foreach ($slots as $check_slot) {
                                                        if ($check_slot['slot_id'] == $slot['slot_id'] && 
                                                            isset($check_slot['appointment_date']) && 
                                                            $check_slot['appointment_date'] == $date_str &&
                                                            in_array($check_slot['status'], ['pending', 'accepted'])) {
                                                            $is_booked = true;
                                                            break;
                                                        }
                                                    }
                                                    
                                                    $dates[] = [
                                                        'date' => $date_str,
                                                        'display' => $display_date,
                                                        'is_booked' => $is_booked
                                                    ];
                                                    
                                                    $current_date->modify('+7 days');
                                                }
                                                ?>
                                                
                                                <select name="appointment_date" class="date-select" required>
                                                    <option value="">Select a date</option>
                                                    <?php foreach ($dates as $date): ?>
                                                        <option value="<?php echo $date['date']; ?>" <?php echo $date['is_booked'] ? 'disabled' : ''; ?>>
                                                            <?php echo $date['display']; ?>
                                                            <?php echo $date['is_booked'] ? ' (Booked)' : ''; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                
                                                <button type="submit" name="book_slot" class="btn btn-small <?php echo count(array_filter($dates, function($d) { return !$d['is_booked']; })) === 0 ? 'btn-disabled' : ''; ?>" <?php echo count(array_filter($dates, function($d) { return !$d['is_booked']; })) === 0 ? 'disabled' : ''; ?>>
                                                    Book
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <!-- This column is filled by the form in the previous column -->
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-slots">No viewing slots available for this flat.</p>
                    <?php endif; ?>
                </div>
                
                <div class="appointment-actions">
                    <a href="flat-detail.php?ref=<?php echo $flat_ref; ?>" class="btn btn-secondary">Back to Flat Details</a>
                </div>
            </section>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
