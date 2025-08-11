<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once 'database.inc.php';

// Get sorting preferences from cookies
$sortColumn = isset($_COOKIE['msg_sort_column']) ? $_COOKIE['msg_sort_column'] : 'created_at';
$sortOrder = isset($_COOKIE['msg_sort_order']) ? $_COOKIE['msg_sort_order'] : 'DESC';

// Handle new sort request
if (isset($_GET['sort'])) {
    $newSortColumn = $_GET['sort'];
    
    // If clicking the same column, toggle the order
    if ($newSortColumn === $sortColumn) {
        $sortOrder = ($sortOrder === 'ASC') ? 'DESC' : 'ASC';
    } else {
        $sortColumn = $newSortColumn;
        $sortOrder = 'DESC'; // Default to newest first for new column
    }
    
    // Set cookies for 30 days
    setcookie('msg_sort_column', $sortColumn, time() + (86400 * 30), "/");
    setcookie('msg_sort_order', $sortOrder, time() + (86400 * 30), "/");
}

// Mark message as read
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE message_id = :message_id AND receiver_id = :receiver_id");
    $stmt->execute(['message_id' => $_GET['read'], 'receiver_id' => $_SESSION['user_id']]);
    
    // Redirect to remove the query parameter
    header('Location: messages.php');
    exit;
}

// Get messages for the current user
$stmt = $pdo->prepare("
    SELECT m.*, u.name as sender_name
    FROM messages m
    JOIN users u ON m.sender_id = u.user_id
    WHERE m.receiver_id = :user_id
    ORDER BY m.$sortColumn $sortOrder
");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$messages = $stmt->fetchAll();

// Count unread messages
$unread_count = 0;
foreach ($messages as $message) {
    if (!$message['is_read']) {
        $unread_count++;
    }
}

// Process message actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept appointment
    if (isset($_POST['accept_appointment']) && isset($_POST['appointment_id'])) {
        $appointment_id = $_POST['appointment_id'];
        
        // Update appointment status
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'accepted' 
            WHERE appointment_id = :appointment_id
        ");
        $stmt->execute(['appointment_id' => $appointment_id]);
        
        // Get appointment details
        $stmt = $pdo->prepare("
            SELECT a.*, u.name as customer_name, u.user_id as customer_id, f.flat_ref
            FROM appointments a
            JOIN users u ON a.customer_id = u.user_id
            JOIN viewing_slots vs ON a.slot_id = vs.slot_id
            JOIN flats f ON vs.flat_ref = f.flat_ref
            WHERE a.appointment_id = :appointment_id
        ");
        $stmt->execute(['appointment_id' => $appointment_id]);
        $appointment = $stmt->fetch();
        
        // Create notification for customer
        $stmt = $pdo->prepare("
            INSERT INTO messages (
                sender_id, receiver_id, title, body, related_entity_type, related_entity_id
            ) VALUES (
                :sender_id, :receiver_id, :title, :body, 'appointment', :appointment_id
            )
        ");
        
        $stmt->execute([
            'sender_id' => $_SESSION['user_id'],
            'receiver_id' => $appointment['customer_id'],
            'title' => 'Viewing Appointment Confirmed',
            'body' => 'Your appointment to view flat (Ref: ' . $appointment['flat_ref'] . ') on ' . date('d/m/Y', strtotime($appointment['appointment_date'])) . ' has been confirmed.',
            'appointment_id' => $appointment_id
        ]);
        
        // Redirect to refresh the page
        header('Location: messages.php?success=appointment_accepted');
        exit;
    }
    
    // Reject appointment
    elseif (isset($_POST['reject_appointment']) && isset($_POST['appointment_id'])) {
        $appointment_id = $_POST['appointment_id'];
        
        // Update appointment status
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET status = 'rejected' 
            WHERE appointment_id = :appointment_id
        ");
        $stmt->execute(['appointment_id' => $appointment_id]);
        
        // Get appointment details
        $stmt = $pdo->prepare("
            SELECT a.*, u.name as customer_name, u.user_id as customer_id, f.flat_ref
            FROM appointments a
            JOIN users u ON a.customer_id = u.user_id
            JOIN viewing_slots vs ON a.slot_id = vs.slot_id
            JOIN flats f ON vs.flat_ref = f.flat_ref
            WHERE a.appointment_id = :appointment_id
        ");
        $stmt->execute(['appointment_id' => $appointment_id]);
        $appointment = $stmt->fetch();
        
        // Create notification for customer
        $stmt = $pdo->prepare("
            INSERT INTO messages (
                sender_id, receiver_id, title, body, related_entity_type, related_entity_id
            ) VALUES (
                :sender_id, :receiver_id, :title, :body, 'appointment', :appointment_id
            )
        ");
        
        $stmt->execute([
            'sender_id' => $_SESSION['user_id'],
            'receiver_id' => $appointment['customer_id'],
            'title' => 'Viewing Appointment Rejected',
            'body' => 'Your appointment to view flat (Ref: ' . $appointment['flat_ref'] . ') on ' . date('d/m/Y', strtotime($appointment['appointment_date'])) . ' has been rejected.',
            'appointment_id' => $appointment_id
        ]);
        
        // Redirect to refresh the page
        header('Location: messages.php?success=appointment_rejected');
        exit;
    }
    
    // Approve flat
    elseif (isset($_POST['approve_flat']) && isset($_POST['flat_ref'])) {
        $flat_ref = $_POST['flat_ref'];
        
        // Update flat status
        $stmt = $pdo->prepare("
            UPDATE flats 
            SET status = 'approved' 
            WHERE flat_ref = :flat_ref
        ");
        $stmt->execute(['flat_ref' => $flat_ref]);
        
        // Get flat details
        $stmt = $pdo->prepare("
            SELECT f.*, u.name as owner_name, u.user_id as owner_id
            FROM flats f
            JOIN users u ON f.owner_id = u.user_id
            WHERE f.flat_ref = :flat_ref
        ");
        $stmt->execute(['flat_ref' => $flat_ref]);
        $flat = $stmt->fetch();
        
        // Create notification for owner
        $stmt = $pdo->prepare("
            INSERT INTO messages (
                sender_id, receiver_id, title, body, related_entity_type, related_entity_id
            ) VALUES (
                :sender_id, :receiver_id, :title, :body, 'flat', :flat_ref
            )
        ");
        
        $stmt->execute([
            'sender_id' => $_SESSION['user_id'],
            'receiver_id' => $flat['owner_id'],
            'title' => 'Flat Approved',
            'body' => 'Your flat (Ref: ' . $flat_ref . ') has been approved and is now listed on the website.',
            'flat_ref' => $flat_ref
        ]);
        
        // Redirect to refresh the page
        header('Location: messages.php?success=flat_approved');
        exit;
    }
    
    // Reject flat
    elseif (isset($_POST['reject_flat']) && isset($_POST['flat_ref'])) {
        $flat_ref = $_POST['flat_ref'];
        
        // Update flat status
        $stmt = $pdo->prepare("
            UPDATE flats 
            SET status = 'unavailable' 
            WHERE flat_ref = :flat_ref
        ");
        $stmt->execute(['flat_ref' => $flat_ref]);
        
        // Get flat details
        $stmt = $pdo->prepare("
            SELECT f.*, u.name as owner_name, u.user_id as owner_id
            FROM flats f
            JOIN users u ON f.owner_id = u.user_id
            WHERE f.flat_ref = :flat_ref
        ");
        $stmt->execute(['flat_ref' => $flat_ref]);
        $flat = $stmt->fetch();
        
        // Create notification for owner
        $stmt = $pdo->prepare("
            INSERT INTO messages (
                sender_id, receiver_id, title, body, related_entity_type, related_entity_id
            ) VALUES (
                :sender_id, :receiver_id, :title, :body, 'flat', :flat_ref
            )
        ");
        
        $stmt->execute([
            'sender_id' => $_SESSION['user_id'],
            'receiver_id' => $flat['owner_id'],
            'title' => 'Flat Rejected',
            'body' => 'Your flat (Ref: ' . $flat_ref . ') has been rejected. Please contact the manager for more information.',
            'flat_ref' => $flat_ref
        ]);
        
        // Redirect to refresh the page
        header('Location: messages.php?success=flat_rejected');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <main>
            <section class="messages-section">
                <h1>Messages</h1>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="success-message">
                        <?php if ($_GET['success'] === 'appointment_accepted'): ?>
                            <p>Appointment has been accepted successfully.</p>
                        <?php elseif ($_GET['success'] === 'appointment_rejected'): ?>
                            <p>Appointment has been rejected.</p>
                        <?php elseif ($_GET['success'] === 'flat_approved'): ?>
                            <p>Flat has been approved successfully.</p>
                        <?php elseif ($_GET['success'] === 'flat_rejected'): ?>
                            <p>Flat has been rejected.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="messages-header">
                    <h2>Your Messages (<?php echo count($messages); ?>)</h2>
                    <?php if ($unread_count > 0): ?>
                        <span class="unread-count"><?php echo $unread_count; ?> unread</span>
                    <?php endif; ?>
                </div>
                
                <?php if (count($messages) > 0): ?>
                    <table class="messages-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>
                                    <a href="?sort=sender_name">
                                        From
                                        <?php if ($sortColumn === 'sender_name'): ?>
                                            <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=title">
                                        Title
                                        <?php if ($sortColumn === 'title'): ?>
                                            <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=created_at">
                                        Date
                                        <?php if ($sortColumn === 'created_at'): ?>
                                            <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $index => $message): ?>
                                <tr class="<?php echo $index % 2 === 0 ? 'even-row' : 'odd-row'; ?> <?php echo !$message['is_read'] ? 'unread-message' : ''; ?>">
                                    <td class="message-status">
                                        <?php if (!$message['is_read']): ?>
                                            <span class="unread-icon">●</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($message['sender_name']); ?></td>
                                    <td>
                                        <a href="#message-<?php echo $message['message_id']; ?>" class="message-title-link" data-message-id="<?php echo $message['message_id']; ?>">
                                            <?php echo htmlspecialchars($message['title']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?></td>
                                    <td>
                                        <?php if (!$message['is_read']): ?>
                                            <a href="messages.php?read=<?php echo $message['message_id']; ?>" class="btn btn-small">Mark as Read</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr id="message-<?php echo $message['message_id']; ?>" class="message-content-row" style="display: none;">
                                    <td colspan="5">
                                        <div class="message-content">
                                            <p><?php echo nl2br(htmlspecialchars($message['body'])); ?></p>
                                            
                                            <?php
                                            // Handle related entities
                                            if ($message['related_entity_type'] === 'appointment' && $_SESSION['user_type'] === 'owner') {
                                                // Get appointment details
                                                $stmt = $pdo->prepare("
                                                    SELECT a.*, u.name as customer_name, vs.day_of_week, vs.start_time, vs.end_time, f.flat_ref, f.location
                                                    FROM appointments a
                                                    JOIN users u ON a.customer_id = u.user_id
                                                    JOIN viewing_slots vs ON a.slot_id = vs.slot_id
                                                    JOIN flats f ON vs.flat_ref = f.flat_ref
                                                    WHERE a.appointment_id = :appointment_id
                                                ");
                                                $stmt->execute(['appointment_id' => $message['related_entity_id']]);
                                                $appointment = $stmt->fetch();
                                                
                                                if ($appointment && $appointment['status'] === 'pending') {
                                                    echo '<div class="message-actions">';
                                                    echo '<h4>Appointment Details:</h4>';
                                                    echo '<p><strong>Flat:</strong> ' . htmlspecialchars($appointment['flat_ref']) . ' - ' . htmlspecialchars($appointment['location']) . '</p>';
                                                    echo '<p><strong>Customer:</strong> ' . htmlspecialchars($appointment['customer_name']) . '</p>';
                                                    echo '<p><strong>Date:</strong> ' . date('d/m/Y', strtotime($appointment['appointment_date'])) . '</p>';
                                                    echo '<p><strong>Time:</strong> ' . date('H:i', strtotime($appointment['start_time'])) . ' - ' . date('H:i', strtotime($appointment['end_time'])) . '</p>';
                                                    
                                                    echo '<form action="messages.php" method="POST" class="inline-form">';
                                                    echo '<input type="hidden" name="appointment_id" value="' . $appointment['appointment_id'] . '">';
                                                    echo '<button type="submit" name="accept_appointment" class="btn btn-small">Accept</button>';
                                                    echo '<button type="submit" name="reject_appointment" class="btn btn-small btn-danger">Reject</button>';
                                                    echo '</form>';
                                                    echo '</div>';
                                                }
                                            }
                                            elseif ($message['related_entity_type'] === 'flat' && $_SESSION['user_type'] === 'manager' && strpos($message['title'], 'Pending Approval') !== false) {
                                                // Get flat details
                                                $stmt = $pdo->prepare("
                                                    SELECT f.*, u.name as owner_name
                                                    FROM flats f
                                                    JOIN users u ON f.owner_id = u.user_id
                                                    WHERE f.flat_ref = :flat_ref
                                                ");
                                                $stmt->execute(['flat_ref' => $message['related_entity_id']]);
                                                $flat = $stmt->fetch();
                                                
                                                if ($flat && $flat['status'] === 'pending') {
                                                    echo '<div class="message-actions">';
                                                    echo '<h4>Flat Details:</h4>';
                                                    echo '<p><strong>Reference:</strong> ' . htmlspecialchars($flat['flat_ref']) . '</p>';
                                                    echo '<p><strong>Location:</strong> ' . htmlspecialchars($flat['location']) . '</p>';
                                                    echo '<p><strong>Owner:</strong> ' . htmlspecialchars($flat['owner_name']) . '</p>';
                                                    echo '<p><strong>Monthly Cost:</strong> $' . number_format($flat['monthly_cost'], 2) . '</p>';
                                                    echo '<p><a href="flat-detail.php?ref=' . $flat['flat_ref'] . '&preview=1" target="_blank" class="btn btn-small">View Details</a></p>';
                                                    
                                                    echo '<form action="messages.php" method="POST" class="inline-form">';
                                                    echo '<input type="hidden" name="flat_ref" value="' . $flat['flat_ref'] . '">';
                                                    echo '<button type="submit" name="approve_flat" class="btn btn-small">Approve</button>';
                                                    echo '<button type="submit" name="reject_flat" class="btn btn-small btn-danger">Reject</button>';
                                                    echo '</form>';
                                                    echo '</div>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-messages">You have no messages.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle message expansion
            const messageLinks = document.querySelectorAll('.message-title-link');
            
            messageLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const messageId = this.getAttribute('data-message-id');
                    const contentRow = document.getElementById('message-' + messageId);
                    
                    // Toggle visibility
                    if (contentRow.style.display === 'none' || contentRow.style.display === '') {
                        contentRow.style.display = 'table-row';
                        
                        // Mark as read if unread
                        if (this.closest('tr').classList.contains('unread-message')) {
                            // Send AJAX request to mark as read
                            fetch('messages.php?read=' + messageId)
                                .then(response => {
                                    if (response.ok) {
                                        this.closest('tr').classList.remove('unread-message');
                                        this.closest('tr').querySelector('.unread-icon').style.display = 'none';
                                        
                                        // Update unread count
                                        const unreadCount = document.querySelector('.unread-count');
                                        if (unreadCount) {
                                            const currentCount = parseInt(unreadCount.textContent);
                                            if (currentCount > 1) {
                                                unreadCount.textContent = (currentCount - 1) + ' unread';
                                            } else {
                                                unreadCount.remove();
                                            }
                                        }
                                    }
                                });
                        }
                    } else {
                        contentRow.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>
