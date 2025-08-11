<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Birzeit Flat Rent - Home</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <section class="content-area">
            <section class="hero">
                <h1>Find Your Perfect Flat in Birzeit</h1>
                <p>Browse through our extensive collection of quality flats for rent</p>
                <nav class="cta-buttons">
                    <a href="search.php" class="btn btn-primary">Search Flats</a>
                    <a href="register-customer.php" class="btn btn-secondary">Register as Customer</a>
                    <a href="register-owner.php" class="btn btn-secondary">Register as Owner</a>
                </nav>
            </section>
            
            <section class="featured-flats">
                <h2>Featured Flats</h2>
                <ul class="flat-grid">
                    <?php
                    // Include database connection
                    require_once 'database.inc.php';
                    
                    // Get featured flats (most recent approved flats)
                    $stmt = $pdo->prepare("
                        SELECT f.flat_ref, f.location, f.monthly_cost, f.bedrooms, f.bathrooms, p.photo_path 
                        FROM flats f
                        LEFT JOIN flat_photos p ON f.flat_ref = p.flat_ref AND p.is_primary = 1
                        WHERE f.status = 'approved'
                        ORDER BY f.created_at DESC
                        LIMIT 6
                    ");
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        while ($flat = $stmt->fetch()) {
                            echo '<li class="flat-card">';
                            echo '<figure>';
                            echo '<a href="flat-detail.php?ref=' . $flat['flat_ref'] . '">';
                            if (!empty($flat['photo_path'])) {
                                echo '<img src="' . $flat['photo_path'] . '" alt="Flat ' . $flat['flat_ref'] . '">';
                            } else {
                                echo '<img src="/placeholder.svg?height=200&width=300" alt="No image available">';
                            }
                            echo '</a>';
                            echo '<figcaption>Ref: ' . $flat['flat_ref'] . '</figcaption>';
                            echo '</figure>';
                            echo '<article class="flat-info">';
                            echo '<h3>' . $flat['location'] . '</h3>';
                            echo '<p class="price">$' . number_format($flat['monthly_cost'], 2) . '/month</p>';
                            echo '<p>' . $flat['bedrooms'] . ' Bedrooms | ' . $flat['bathrooms'] . ' Bathrooms</p>';
                            echo '<a href="flat-detail.php?ref=' . $flat['flat_ref'] . '" class="btn btn-small">View Details</a>';
                            echo '</article>';
                            echo '</li>';
                        }
                    } else {
                        echo '<li><p>No featured flats available at the moment.</p></li>';
                    }
                    ?>
                </ul>
            </section>
            
            <section class="about-preview">
                <h2>About Birzeit Flat Rent</h2>
                <p>We are the leading flat rental agency in Birzeit, providing high-quality accommodation options for students, professionals, and families.</p>
                <a href="about.php" class="btn btn-text">Learn More About Us</a>
            </section>
        </section>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
