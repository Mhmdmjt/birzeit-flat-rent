<?php
session_start();
require_once 'database.inc.php';

// Get sorting preferences from cookies
$sortColumn = isset($_COOKIE['sort_column']) ? $_COOKIE['sort_column'] : 'monthly_cost';
$sortOrder = isset($_COOKIE['sort_order']) ? $_COOKIE['sort_order'] : 'ASC';

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
    setcookie('sort_column', $sortColumn, time() + (86400 * 30), "/");
    setcookie('sort_order', $sortOrder, time() + (86400 * 30), "/");
}

// Build the query based on search filters
$query = "
    SELECT f.flat_ref, f.location, f.monthly_cost, f.available_from, f.bedrooms, f.bathrooms, 
           f.is_furnished, p.photo_path
    FROM flats f
    LEFT JOIN flat_photos p ON f.flat_ref = p.flat_ref AND p.is_primary = 1
    WHERE f.status = 'approved'
";

$params = [];

// Apply filters if provided
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['filter'])) {
    $filters = $_GET['filter'];
    
    if (!empty($filters['min_price'])) {
        $query .= " AND f.monthly_cost >= :min_price";
        $params['min_price'] = $filters['min_price'];
    }
    
    if (!empty($filters['max_price'])) {
        $query .= " AND f.monthly_cost <= :max_price";
        $params['max_price'] = $filters['max_price'];
    }
    
    if (!empty($filters['location'])) {
        $query .= " AND f.location LIKE :location";
        $params['location'] = '%' . $filters['location'] . '%';
    }
    
    if (!empty($filters['bedrooms'])) {
        $query .= " AND f.bedrooms >= :bedrooms";
        $params['bedrooms'] = $filters['bedrooms'];
    }
    
    if (!empty($filters['bathrooms'])) {
        $query .= " AND f.bathrooms >= :bathrooms";
        $params['bathrooms'] = $filters['bathrooms'];
    }
    
    if (isset($filters['furnished']) && $filters['furnished'] !== '') {
        $query .= " AND f.is_furnished = :furnished";
        $params['furnished'] = $filters['furnished'];
    }
}

// Add sorting
$query .= " ORDER BY f.$sortColumn $sortOrder";

// Prepare and execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$flats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Flats - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <section class="content-area">
            <section class="search-section">
                <h1>Search Available Flats</h1>
                
                <form action="search.php" method="GET" class="search-form">
                    <fieldset class="form-group">
                        <label for="location">Location:</label>
                        <input type="text" id="location" name="filter[location]" value="<?php echo isset($_GET['filter']['location']) ? htmlspecialchars($_GET['filter']['location']) : ''; ?>">
                    </fieldset>
                    
                    <fieldset class="form-row">
                        <section class="form-group">
                            <label for="min_price">Min Price ($):</label>
                            <input type="number" id="min_price" name="filter[min_price]" value="<?php echo isset($_GET['filter']['min_price']) ? htmlspecialchars($_GET['filter']['min_price']) : ''; ?>">
                        </section>
                        
                        <section class="form-group">
                            <label for="max_price">Max Price ($):</label>
                            <input type="number" id="max_price" name="filter[max_price]" value="<?php echo isset($_GET['filter']['max_price']) ? htmlspecialchars($_GET['filter']['max_price']) : ''; ?>">
                        </section>
                    </fieldset>
                    
                    <fieldset class="form-row">
                        <section class="form-group">
                            <label for="bedrooms">Bedrooms:</label>
                            <select id="bedrooms" name="filter[bedrooms]">
                                <option value="">Any</option>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo (isset($_GET['filter']['bedrooms']) && $_GET['filter']['bedrooms'] == $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>+
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </section>
                        
                        <section class="form-group">
                            <label for="bathrooms">Bathrooms:</label>
                            <select id="bathrooms" name="filter[bathrooms]">
                                <option value="">Any</option>
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo (isset($_GET['filter']['bathrooms']) && $_GET['filter']['bathrooms'] == $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?>+
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </section>
                        
                        <section class="form-group">
                            <label for="furnished">Furnished:</label>
                            <select id="furnished" name="filter[furnished]">
                                <option value="">Any</option>
                                <option value="1" <?php echo (isset($_GET['filter']['furnished']) && $_GET['filter']['furnished'] == '1') ? 'selected' : ''; ?>>Yes</option>
                                <option value="0" <?php echo (isset($_GET['filter']['furnished']) && $_GET['filter']['furnished'] == '0') ? 'selected' : ''; ?>>No</option>
                            </select>
                        </section>
                    </fieldset>
                    
                    <section class="form-actions">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <button type="reset" class="btn btn-secondary">Reset</button>
                    </section>
                </form>
                
                <section class="search-results">
                    <h2>Available Flats</h2>
                    
                    <?php if (count($flats) > 0): ?>
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>
                                        <a href="?sort=flat_ref<?php echo isset($_GET['filter']) ? '&' . http_build_query(['filter' => $_GET['filter']]) : ''; ?>">
                                            Flat Reference
                                            <?php if ($sortColumn === 'flat_ref'): ?>
                                                <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=monthly_cost<?php echo isset($_GET['filter']) ? '&' . http_build_query(['filter' => $_GET['filter']]) : ''; ?>">
                                            Monthly Cost
                                            <?php if ($sortColumn === 'monthly_cost'): ?>
                                                <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=available_from<?php echo isset($_GET['filter']) ? '&' . http_build_query(['filter' => $_GET['filter']]) : ''; ?>">
                                            Available From
                                            <?php if ($sortColumn === 'available_from'): ?>
                                                <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=location<?php echo isset($_GET['filter']) ? '&' . http_build_query(['filter' => $_GET['filter']]) : ''; ?>">
                                            Location
                                            <?php if ($sortColumn === 'location'): ?>
                                                <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?sort=bedrooms<?php echo isset($_GET['filter']) ? '&' . http_build_query(['filter' => $_GET['filter']]) : ''; ?>">
                                            Bedrooms
                                            <?php if ($sortColumn === 'bedrooms'): ?>
                                                <span class="sort-icon"><?php echo ($sortOrder === 'ASC') ? '▲' : '▼'; ?></span>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>Photo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($flats as $index => $flat): ?>
                                    <tr class="<?php echo $index % 2 === 0 ? 'even-row' : 'odd-row'; ?>">
                                        <td><?php echo htmlspecialchars($flat['flat_ref']); ?></td>
                                        <td>$<?php echo number_format($flat['monthly_cost'], 2); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($flat['available_from'])); ?></td>
                                        <td><?php echo htmlspecialchars($flat['location']); ?></td>
                                        <td><?php echo htmlspecialchars($flat['bedrooms']); ?></td>
                                        <td>
                                            <a href="flat-detail.php?ref=<?php echo $flat['flat_ref']; ?>" target="_blank">
                                                <?php if (!empty($flat['photo_path'])): ?>
                                                    <img src="<?php echo $flat['photo_path']; ?>" alt="Flat <?php echo $flat['flat_ref']; ?>" class="thumbnail">
                                                <?php else: ?>
                                                    <img src="/placeholder.svg?height=80&width=120" alt="No image available" class="thumbnail">
                                                <?php endif; ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="no-results">No flats found matching your criteria. Please try different search parameters.</p>
                    <?php endif; ?>
                </section>
            </section>
        </section>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
