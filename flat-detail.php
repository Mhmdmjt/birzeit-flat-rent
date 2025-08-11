<?php
session_start();
require 'database.inc.php';

// Check if flat reference is provided
if (!isset($_GET['ref'])) {
    header('Location: search.php');
    exit;
}

$flat_ref = $_GET['ref'];

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

// Get flat photos
$stmt = $pdo->prepare("SELECT photo_path FROM flat_photos WHERE flat_ref = :flat_ref ORDER BY is_primary DESC");
$stmt->execute(['flat_ref' => $flat_ref]);
$photos = $stmt->fetchAll();

// Get marketing information
$stmt = $pdo->prepare("SELECT * FROM marketing_info WHERE flat_ref = :flat_ref");
$stmt->execute(['flat_ref' => $flat_ref]);
$marketing_info = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flat Details - <?php echo $flat['flat_ref']; ?> - Birzeit Flat Rent</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <?php include 'includes/navigation.php'; ?>
        
        <section class="content-area flat-detail-page">
            <h1>Flat Details - Ref: <?php echo $flat['flat_ref']; ?></h1>
            
            <article class="flatcard">
                <section class="flat-photos">
                    <?php if (count($photos) > 0): ?>
                        <?php foreach ($photos as $index => $photo): ?>
                            <figure class="flat-photo <?php echo $index === 0 ? 'main-photo' : ''; ?>">
                                <img src="<?php echo $photo['photo_path']; ?>" alt="Flat <?php echo $flat['flat_ref']; ?> - Photo <?php echo $index + 1; ?>">
                            </figure>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <figure class="flat-photo main-photo">
                            <img src="C:\Users\a-z\Downloads\تنزيل.jpg" alt="No image available">
                        </figure>
                    <?php endif; ?>
                </section>
                
                <section class="flat-description">
                    <h2><?php echo htmlspecialchars($flat['location']); ?></h2>
                    <p class="price">$<?php echo number_format($flat['monthly_cost'], 2); ?> per month</p>
                    
                    <section class="flat-details">
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($flat['address']); ?></p>
                        <p><strong>Available From:</strong> <?php echo date('d/m/Y', strtotime($flat['available_from'])); ?></p>
                        <p><strong>Available To:</strong> <?php echo date('d/m/Y', strtotime($flat['available_to'])); ?></p>
                        <p><strong>Bedrooms:</strong> <?php echo $flat['bedrooms']; ?></p>
                        <p><strong>Bathrooms:</strong> <?php echo $flat['bathrooms']; ?></p>
                        <p><strong>Size:</strong> <?php echo $flat['size_sqm']; ?> m²</p>
                        <p><strong>Furnished:</strong> <?php echo $flat['is_furnished'] ? 'Yes' : 'No'; ?></p>
                        
                        <section class="features">
                            <h3>Features</h3>
                            <ul>
                                <?php if ($flat['has_heating']): ?><li>Heating System</li><?php endif; ?>
                                <?php if ($flat['has_ac']): ?><li>Air Conditioning</li><?php endif; ?>
                                <?php if ($flat['has_access_control']): ?><li>Access Control</li><?php endif; ?>
                                <?php if ($flat['has_parking']): ?><li>Parking</li><?php endif; ?>
                                <?php if ($flat['has_backyard'] !== 'none'): ?>
                                    <li>Backyard (<?php echo $flat['has_backyard']; ?>)</li>
                                <?php endif; ?>
                                <?php if ($flat['has_playground']): ?><li>Playground</li><?php endif; ?>
                                <?php if ($flat['has_storage']): ?><li>Storage</li><?php endif; ?>
                            </ul>
                        </section>
                        
                        <?php if (!empty($flat['rental_conditions'])): ?>
                            <section class="rental-conditions">
                                <h3>Rental Conditions</h3>
                                <p><?php echo nl2br(htmlspecialchars($flat['rental_conditions'])); ?></p>
                            </section>
                        <?php endif; ?>
                    </section>
                    
                    <section class="flat-actions">
                        <a href="rent-flat.php?ref=<?php echo $flat['flat_ref']; ?>" class="btn btn-primary">Rent This Flat</a>
                        <a href="request-appointment.php?ref=<?php echo $flat['flat_ref']; ?>" class="btn btn-secondary">Request Viewing Appointment</a>
                    </section>
                </section>
            </article>
            
            <aside class="marketing-info">
                <h3>Nearby Places</h3>
                <?php if (count($marketing_info) > 0): ?>
                    <ul class="nearby-places">
                        <?php foreach ($marketing_info as $info): ?>
                            <li>
                                <h4><?php echo htmlspecialchars($info['title']); ?></h4>
                                <p><?php echo htmlspecialchars($info['description']); ?></p>
                                <?php if (!empty($info['url'])): ?>
                                    <a href="<?php echo $info['url']; ?>" target="_blank" class="external-link">Visit Website</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No nearby places information available.</p>
                <?php endif; ?>
            </aside>
            
            <nav class="side-nav">
                <ul>
                    <li><a href="request-appointment.php?ref=<?php echo $flat['flat_ref']; ?>">Request Flat Viewing Appointment</a></li>
                    <li><a href="rent-flat.php?ref=<?php echo $flat['flat_ref']; ?>">Rent the Flat</a></li>
                    <li><a href="search.php">Back to Search</a></li>
                </ul>
            </nav>
        </section>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
