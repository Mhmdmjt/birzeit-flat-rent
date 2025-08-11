<?php
session_start();

// Check if user is logged in as a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once 'database.inc.php';

// Get sorting preferences from cookies
$sortColumn = isset($_COOKIE['rental_sort_column']) ? $_COOKIE['rental_sort_column'] : 'start_date';
$sortOrder = isset($_COOKIE['rental_sort_order']) ? $_COOKIE['rental_sort_order'] : 'DESC';

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
    setcookie('rental_sort_column', $sortColumn, time() + (86400 * 30), "/");
    setcookie('rental_sort_order', $sortOrder, time() + (86400 * 30), "/");
}

// Get current date for comparison
$current_date = date('Y-m-d');

// Get all rentals for the current customer
$stmt = $pdo->prepare("
    SELECT r.*, f.location, f.address, u.name as owner_name, u.user_id as owner_id
    FROM rentals r
    JOIN flats f ON r.flat_ref = f.flat_ref
    JOIN users u ON f.owner_id = u.user_id
    WHERE r.customer_id = :customer_id
    ORDER BY r.$sortColumn $sortOrder
");
$stmt->execute(['customer_id' => $_SESSION['user_id']]);
$rentals = $stmt->fetchAll();

// Separate rentals into current and past
$current_rentals = [];
$past_rentals = [];

foreach ($rentals as $rental) {
    if ($rental['end_date'] >= $current_date) {
        $current_rentals[] = $rental;
    } else {
        $past_rentals[] = $rental;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Rentals - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <main>
            <section class="rentals-section">
                <h1>My Rented Flats</h1>
                
                <div class="rental-tabs">
                    <button class="tab-button active" data-tab="current">Current Rentals (<?php echo count($current_rentals); ?>)</button>
                    <button class="tab-button" data-tab="past">Past Rentals (<?php echo count($past_rentals); ?>)</button>
                </div>
                
                <div id="current-rentals" class="tab-content active">
                    <h2>Current Rentals</h2>
                    
                    <?php if (count($current_rentals) > 0): ?>
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
                                        <a href="?sort=monthly_cost">
                                            Monthly Cost
                                            <?php if ($sortColumn === 'monthly_cost'): ?>
                                                <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=start_date">
                                            Start Date
                                            <?php if ($sortColumn === 'start_date'): ?>
                                                <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=end_date">
                                            End Date
                                            <?php if ($sortColumn === 'end_date'): ?>
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
                                    <th>Owner</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($current_rentals as $index => $rental): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'even-row' : 'odd-row'; ?> current-rental">
                                        <td>
                                            <a href="flat-detail.php?ref=<?php echo $rental['flat_ref']; ?>" target="_blank" class="flat-ref-link">
                                                <?php echo htmlspecialchars($rental['flat_ref']); ?>
                                            </a>
                                        </td>
                                        <td>$<?php echo number_format($rental['total_cost'] / ceil((strtotime($rental['end_date']) - strtotime($rental['start_date'])) / (60*60*24*30)), 2); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($rental['start_date'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($rental['end_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($rental['location']); ?></td>
                                        <td>
                                            <a href="user-card.php?id=<?php echo $rental['owner_id']; ?>" target="_blank" class="user-link">
                                                <?php echo htmlspecialchars($rental['owner_name']); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-results">You have no current rentals.</p>
                    <?php endif; ?>
                </div>
                
                <div id="past-rentals" class="tab-content">
                    <h2>Past Rentals</h2>
                    
                    <?php if (count($past_rentals) > 0): ?>
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
                                        <a href="?sort=monthly_cost">
                                            Monthly Cost
                                            <?php if ($sortColumn === 'monthly_cost'): ?>
                                                <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=start_date">
                                            Start Date
                                            <?php if ($sortColumn === 'start_date'): ?>
                                                <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=end_date">
                                            End Date
                                            <?php if ($sortColumn === 'end_date'): ?>
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
                                    <th>Owner</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($past_rentals as $index => $rental): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'even-row' : 'odd-row'; ?> past-rental">
                                        <td>
                                            <a href="flat-detail.php?ref=<?php echo $rental['flat_ref']; ?>" target="_blank" class="flat-ref-link">
                                                <?php echo htmlspecialchars($rental['flat_ref']); ?>
                                            </a>
                                        </td>
                                        <td>$<?php echo number_format($rental['total_cost'] / ceil((strtotime($rental['end_date']) - strtotime($rental['start_date'])) / (60*60*24*30)), 2); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($rental['start_date'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($rental['end_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($rental['location']); ?></td>
                                        <td>
                                            <a href="user-card.php?id=<?php echo $rental['owner_id']; ?>" target="_blank" class="user-link">
                                                <?php echo htmlspecialchars($rental['owner_name']); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-results">You have no past rentals.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons and contents
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked button and corresponding content
                    this.classList.add('active');
                    document.getElementById(this.dataset.tab + '-rentals').classList.add('active');
                });
            });
        });
    </script>
</body>
</html>
