<?php
session_start();

// Check if user is logged in as an owner
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'owner') {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once 'database.inc.php';

// Get sorting preferences from cookies
$sortColumn = isset($_COOKIE['my_flats_sort_column']) ? $_COOKIE['my_flats_sort_column'] : 'created_at';
$sortOrder = isset($_COOKIE['my_flats_sort_order']) ? $_COOKIE['my_flats_sort_order'] : 'DESC';

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
    setcookie('my_flats_sort_column', $sortColumn, time() + (86400 * 30), "/");
    setcookie('my_flats_sort_order', $sortOrder, time() + (86400 * 30), "/");
}

// Get all flats for the current owner
$stmt = $pdo->prepare("
    SELECT f.*, 
           (SELECT COUNT(*) FROM rentals r WHERE r.flat_ref = f.flat_ref AND r.status = 'confirmed') as rental_count
    FROM flats f
    WHERE f.owner_id = :owner_id
    ORDER BY f.$sortColumn $sortOrder
");
$stmt->execute(['owner_id' => $_SESSION['user_id']]);
$flats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Flats - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <section class="content-area">
            <section class="my-flats-section">
                <h1>My Flats</h1>
                
                <section class="section-header">
                    <p>Manage your flats and view their rental status.</p>
                    <a href="offer-flat.php" class="btn btn-primary">Offer New Flat</a>
                </section>
                
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
                                    <a href="?sort=available_from">
                                        Available From
                                        <?php if ($sortColumn === 'available_from'): ?>
                                            <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=available_to">
                                        Available To
                                        <?php if ($sortColumn === 'available_to'): ?>
                                            <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?sort=status">
                                        Status
                                        <?php if ($sortColumn === 'status'): ?>
                                            <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                        <?php endif; ?>
                                    </a>
                                </th>
                                <th>Rentals</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($flats as $index => $flat): ?>
                                <tr class="<?php echo $index % 2 === 0 ? 'even-row' : 'odd-row'; ?>">
                                    <td><?php echo htmlspecialchars($flat['flat_ref']); ?></td>
                                    <td><?php echo htmlspecialchars($flat['location']); ?></td>
                                    <td>$<?php echo number_format($flat['monthly_cost'], 2); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($flat['available_from'])); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($flat['available_to'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($flat['status']); ?>">
                                            <?php echo ucfirst($flat['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $flat['rental_count']; ?></td>
                                    <td>
                                        <a href="flat-detail.php?ref=<?php echo $flat['flat_ref']; ?>" class="btn btn-small">View</a>
                                        <?php if ($flat['status'] === 'approved' && $flat['rental_count'] === '0'): ?>
                                            <a href="edit-flat.php?ref=<?php echo $flat['flat_ref']; ?>" class="btn btn-small">Edit</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <section class="no-results">
                        <p>You haven't offered any flats yet.</p>
                        <a href="offer-flat.php" class="btn btn-primary">Offer a Flat</a>
                    </section>
                <?php endif; ?>
            </section>
        </section>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
