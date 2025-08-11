<?php
session_start();

// Check if user is logged in as a manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'manager') {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once 'database.inc.php';

// Get sorting preferences from cookies
$sortColumn = isset($_COOKIE['approve_sort_column']) ? $_COOKIE['approve_sort_column'] : 'created_at';
$sortOrder = isset($_COOKIE['approve_sort_order']) ? $_COOKIE['approve_sort_order'] : 'DESC';

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
    setcookie('approve_sort_column', $sortColumn, time() + (86400 * 30), "/");
    setcookie('approve_sort_order', $sortOrder, time() + (86400 * 30), "/");
}

// Process approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve']) && isset($_POST['flat_ref'])) {
        $flat_ref = $_POST['flat_ref'];
        
        // Update flat status
        $stmt = $pdo->prepare("UPDATE flats SET status = 'approved' WHERE flat_ref = :flat_ref");
        $stmt->execute(['flat_ref' => $flat_ref]);
        
        // Get owner ID
        $stmt = $pdo->prepare("SELECT owner_id FROM flats WHERE flat_ref = :flat_ref");
        $stmt->execute(['flat_ref' => $flat_ref]);
        $owner_id = $stmt->fetchColumn();
        
        // Send notification to owner
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, title, body, related_entity_type, related_entity_id)
            VALUES (:sender_id, :receiver_id, :title, :body, 'flat', :flat_ref)
        ");
        
        $stmt->execute([
            'sender_id' => $_SESSION['user_id'],
            'receiver_id' => $owner_id,
            'title' => 'Flat Approved',
            'body' => 'Your flat (Ref: ' . $flat_ref . ') has been approved and is now listed on the website.',
            'flat_ref' => $flat_ref
        ]);
        
        header('Location: approve-flats.php?success=approved');
        exit;
    }
    elseif (isset($_POST['reject']) && isset($_POST['flat_ref'])) {
        $flat_ref = $_POST['flat_ref'];
        
        // Update flat status
        $stmt = $pdo->prepare("UPDATE flats SET status = 'unavailable' WHERE flat_ref = :flat_ref");
        $stmt->execute(['flat_ref' => $flat_ref]);
        
        // Get owner ID
        $stmt = $pdo->prepare("SELECT owner_id FROM flats WHERE flat_ref = :flat_ref");
        $stmt->execute(['flat_ref' => $flat_ref]);
        $owner_id = $stmt->fetchColumn();
        
        // Send notification to owner
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, title, body, related_entity_type, related_entity_id)
            VALUES (:sender_id, :receiver_id, :title, :body, 'flat', :flat_ref)
        ");
        
        $stmt->execute([
            'sender_id' => $_SESSION['user_id'],
            'receiver_id' => $owner_id,
            'title' => 'Flat Rejected',
            'body' => 'Your flat (Ref: ' . $flat_ref . ') has been rejected. Please contact the manager for more information.',
            'flat_ref' => $flat_ref
        ]);
        
        header('Location: approve-flats.php?success=rejected');
        exit;
    }
}

// Get pending flats
$stmt = $pdo->prepare("
    SELECT f.*, u.name as owner_name
    FROM flats f
    JOIN users u ON f.owner_id = u.user_id
    WHERE f.status = 'pending'
    ORDER BY f.$sortColumn $sortOrder
");
$stmt->execute();
$flats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Flats - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <main>
            <section class="approve-flats-section">
                <h1>Approve Flats</h1>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="success-message">
                        <?php if ($_GET['success'] === 'approved'): ?>
                            <p>Flat has been approved successfully.</p>
                        <?php elseif ($_GET['success'] === 'rejected'): ?>
                            <p>Flat has been rejected.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="section-header">
                    <p>Review and approve flats submitted by owners.</p>
                </div>
                
                <?php if (count($flats) > 0): ?>
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>
                                    <a href="?sort=flat_ref">
                                        Flat Reference
                                        <?php if ($sortColumn === 'flat_ref'): ?>
                                            <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=location">
                                        Location
                                        <?php if ($sortColumn === 'location'): ?>
                                            <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=monthly_cost">
                                        Monthly Cost
                                        <?php if ($sortColumn === 'monthly_cost'): ?>
                                            <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=created_at">
                                        Submitted On
                                        <?php if ($sortColumn === 'created_at'): ?>
                                            <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Owner</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($flats as $index => $flat): ?>
                                <tr class="<?php echo $index % 2 === 0 ? 'even-row' : 'odd-row'; ?>">
                                    <td><?php echo htmlspecialchars($flat['flat_ref']); ?></td>
                                    <td><?php echo htmlspecialchars($flat['location']); ?></td>
                                    <td>$<?php echo number_format($flat['monthly_cost'], 2); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($flat['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($flat['owner_name']); ?></td>
                                    <td>
                                        <a href="flat-detail.php?ref=<?php echo $flat['flat_ref']; ?>&preview=1" target="_blank" class="btn btn-small">View Details</a>
                                        
                                        <form action="approve-flats.php" method="POST" class="inline-form">
                                            <input type="hidden" name="flat_ref" value="<?php echo $flat['flat_ref']; ?>">
                                            <button type="submit" name="approve" class="btn btn-small">Approve</button>
                                            <button type="submit" name="reject" class="btn btn-small btn-danger">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-results">
                        <p>There are no flats pending approval.</p>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
