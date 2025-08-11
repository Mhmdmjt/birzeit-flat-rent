<?php
session_start();

// Check if user is logged in as a manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'manager') {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

require_once 'database.inc.php';

// Get sorting preferences from cookies
$sortColumn = isset($_COOKIE['inquire_sort_column']) ? $_COOKIE['inquire_sort_column'] : 'f.flat_ref';
$sortOrder = isset($_COOKIE['inquire_sort_order']) ? $_COOKIE['inquire_sort_order'] : 'ASC';

// Handle new sort request
if (isset($_GET['sort'])) {
    $newSortColumn = $_GET['sort'];
    
    // If clicking the same column, toggle the order
    if ($newSortColumn === $sortColumn) {
        $sortOrder = ($sortOrder === 'ASC') ? 'DESC' : 'ASC';
    } else {
        $sortColumn = $newSortColumn;
        $sortOrder = 'ASC';
    }
    
    // Set cookies for 30 days
    setcookie('inquire_sort_column', $sortColumn, time() + (86400 * 30), "/");
    setcookie('inquire_sort_order', $sortOrder, time() + (86400 * 30), "/");
}

// Build the query based on search filters
$query = "
    SELECT f.flat_ref, f.location, f.monthly_cost, f.available_from, f.available_to, f.status,
           o.name as owner_name, o.user_id as owner_id,
           r.start_date, r.end_date, 
           c.name as customer_name, c.user_id as customer_id
    FROM flats f
    JOIN users o ON f.owner_id = o.user_id
    LEFT JOIN rentals r ON f.flat_ref = r.flat_ref AND r.status = 'confirmed'
    LEFT JOIN users c ON r.customer_id = c.user_id
";

$where_clauses = [];
$params = [];

// Apply filters if provided
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['filter'])) {
    $filters = $_GET['filter'];
    
    // Filter by date range
    if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
        $where_clauses[] = "(
            (f.available_from <= :date_to AND f.available_to >= :date_from) OR
            (r.start_date <= :date_to AND r.end_date >= :date_from)
        )";
        $params['date_from'] = $filters['date_from'];
        $params['date_to'] = $filters['date_to'];
    }
    
    // Filter by location
    if (!empty($filters['location'])) {
        $where_clauses[] = "f.location LIKE :location";
        $params['location'] = '%' . $filters['location'] . '%';
    }
    
    // Filter by available date
    if (!empty($filters['available_date'])) {
        $where_clauses[] = "f.available_from <= :available_date AND f.available_to >= :available_date";
        $params['available_date'] = $filters['available_date'];
    }
    
    // Filter by owner
    if (!empty($filters['owner_id'])) {
        $where_clauses[] = "f.owner_id = :owner_id";
        $params['owner_id'] = $filters['owner_id'];
    }
    
    // Filter by customer
    if (!empty($filters['customer_id'])) {
        $where_clauses[] = "r.customer_id = :customer_id";
        $params['customer_id'] = $filters['customer_id'];
    }
}

// Add WHERE clause if filters are applied
if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(" AND ", $where_clauses);
}

// Add sorting
$query .= " ORDER BY $sortColumn $sortOrder";

// Prepare and execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$flats = $stmt->fetchAll();

// Get all owners for the filter dropdown
$stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE user_type = 'owner' ORDER BY name");
$stmt->execute();
$owners = $stmt->fetchAll();

// Get all customers for the filter dropdown
$stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE user_type = 'customer' ORDER BY name");
$stmt->execute();
$customers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flat Inquire - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <main>
            <section class="inquire-section">
                <h1>Flat Inquire</h1>
                
                <form action="flat-inquire.php" method="GET" class="search-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_from">Available From:</label>
                            <input type="date" id="date_from" name="filter[date_from]" value="<?php echo isset($_GET['filter']['date_from']) ? htmlspecialchars($_GET['filter']['date_from']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_to">Available To:</label>
                            <input type="date" id="date_to" name="filter[date_to]" value="<?php echo isset($_GET['filter']['date_to']) ? htmlspecialchars($_GET['filter']['date_to']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="location">Location:</label>
                            <input type="text" id="location" name="filter[location]" value="<?php echo isset($_GET['filter']['location']) ? htmlspecialchars($_GET['filter']['location']) : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="available_date">Available On Date:</label>
                            <input type="date" id="available_date" name="filter[available_date]" value="<?php echo isset($_GET['filter']['available_date']) ? htmlspecialchars($_GET['filter']['available_date']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="owner_id">Owner:</label>
                            <select id="owner_id" name="filter[owner_id]">
                                <option value="">Any Owner</option>
                                <?php foreach ($owners as $owner): ?>
                                    <option value="<?php echo $owner['user_id']; ?>" <?php echo (isset($_GET['filter']['owner_id']) && $_GET['filter']['owner_id'] == $owner['user_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($owner['name']); ?> (<?php echo $owner['user_id']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_id">Customer:</label>
                            <select id="customer_id" name="filter[customer_id]">
                                <option value="">Any Customer</option>
                                <?php foreach ($customers as $customer): ?>
                                    <option value="<?php echo $customer['user_id']; ?>" <?php echo (isset($_GET['filter']['customer_id']) && $_GET['filter']['customer_id'] == $customer['user_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($customer['name']); ?> (<?php echo $customer['user_id']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <button type="reset" class="btn btn-secondary">Reset</button>
                    </div>
                </form>
                
                <div class="search-results">
                    <h2>Flats</h2>
                    
                    <?php if (count($flats) > 0): ?>
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>
                                        <a href="?sort=f.flat_ref<?php echo isset($_GET['filter']) ? '&' . http_build_query(['filter' => $_GET['filter']]) : ''; ?>">
                                            Flat Reference
                                            <?php if ($sortColumn === 'f.flat_ref'): ?>
                                                <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=f.monthly_cost<?php echo isset($_GET['filter']) ? '&' . http_build_query(['filter' => $_GET['filter']]) : ''; ?>">
                                            Monthly Cost
                                            <?php if ($sortColumn === 'f.monthly_cost'): ?>
                                                <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=r.start_date<?php echo isset($_GET['filter']) ? '&' . http_build_query(['filter' => $_GET['filter']]) : ''; ?>">
                                            Rental Start
                                            <?php if ($sortColumn === 'r.start_date'): ?>
                                                <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=r.end_date<?php echo isset($_GET['filter']) ? '&' . http_build_query(['filter' => $_GET['filter']]) : ''; ?>">
                                            Rental End
                                            <?php if ($sortColumn === 'r.end_date'): ?>
                                                <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=f.location<?php echo isset($_GET['filter']) ? '&' . http_build_query(['filter' => $_GET['filter']]) : ''; ?>">
                                            Location
                                            <?php if ($sortColumn === 'f.location'): ?>
                                                <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=o.name<?php echo isset($_GET['filter']) ? '&' . http_build_query(['filter' => $_GET['filter']]) : ''; ?>">
                                            Owner
                                            <?php if ($sortColumn === 'o.name'): ?>
                                                <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=c.name<?php echo isset($_GET['filter']) ? '&' . http_build_query(['filter' => $_GET['filter']]) : ''; ?>">
                                            Customer
                                            <?php if ($sortColumn === 'c.name'): ?>
                                                <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($flats as $index => $flat): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'even-row' : 'odd-row'; ?>">
                                        <td>
                                            <a href="flat-detail.php?ref=<?php echo $flat['flat_ref']; ?>" target="_blank" class="flat-ref-link">
                                                <?php echo htmlspecialchars($flat['flat_ref']); ?>
                                            </a>
                                        </td>
                                        <td>$<?php echo number_format($flat['monthly_cost'], 2); ?></td>
                                        <td><?php echo !empty($flat['start_date']) ? date('d/m/Y', strtotime($flat['start_date'])) : '-'; ?></td>
                                        <td><?php echo !empty($flat['end_date']) ? date('d/m/Y', strtotime($flat['end_date'])) : '-'; ?></td>
                                        <td><?php echo htmlspecialchars($flat['location']); ?></td>
                                        <td>
                                            <a href="user-card.php?id=<?php echo $flat['owner_id']; ?>" target="_blank" class="user-link">
                                                <?php echo htmlspecialchars($flat['owner_name']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if (!empty($flat['customer_id'])): ?>
                                                <a href="user-card.php?id=<?php echo $flat['customer_id']; ?>" target="_blank" class="user-link">
                                                    <?php echo htmlspecialchars($flat['customer_name']); ?>
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($flat['status']); ?>">
                                                <?php echo ucfirst($flat['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-results">No flats found matching your criteria. Please try different search parameters.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
