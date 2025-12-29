<?php
require_once 'config.php';
requireLogin();

$conn = getDBConnection();
$success = '';
$error = '';

// Get trip ID
$trip_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($trip_id <= 0) {
    header("Location: my_trips.php");
    exit();
}

// Fetch trip details with user info
$stmt = $conn->prepare("SELECT tp.*, u.name as user_name, u.id as user_id 
                        FROM trip_plan tp 
                        JOIN user u ON tp.user_id = u.id 
                        WHERE tp.place_id = ?");
$stmt->bind_param("i", $trip_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: my_trips.php");
    exit();
}

$trip = $result->fetch_assoc();
$stmt->close();

// Check if current user owns this trip
$is_owner = ($_SESSION['user_id'] == $trip['user_id']);

// Fetch destinations for this trip
$destinations_query = "SELECT dc.* FROM trip_plan_places tpp 
                       JOIN destination_country dc ON tpp.country_name = dc.country_name 
                       WHERE tpp.trip_id = {$trip_id}";
$destinations = $conn->query($destinations_query);

// Fetch tourist places for destinations
$places_query = "SELECT tp.* FROM tourist_place tp 
                 WHERE tp.country_name IN (
                     SELECT country_name FROM trip_plan_places WHERE trip_id = {$trip_id}
                 )";
$places = $conn->query($places_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($trip['title']) ?> - World Tour</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            text-decoration: none;
            color: white;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }
        
        .nav-links a:hover {
            opacity: 0.8;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 2rem;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .trip-header {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .trip-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        
        .trip-title {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .trip-meta {
            color: #666;
            font-size: 1rem;
        }
        
        .trip-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn {
            padding: 0.7rem 1.5rem;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #667eea;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .trip-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .info-icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .info-label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }
        
        .info-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }
        
        .section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: #333;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }
        
        .schedule-box {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            white-space: pre-wrap;
            line-height: 1.8;
            color: #555;
        }
        
        .destinations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .destination-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .destination-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .destination-name {
            font-size: 1.3rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .destination-info {
            color: #666;
            margin-bottom: 0.3rem;
        }
        
        .places-list {
            list-style: none;
            margin-top: 1rem;
        }
        
        .place-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .place-item:last-child {
            border-bottom: none;
        }
        
        .badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-primary {
            background: #d1e7fd;
            color: #004085;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        @media (max-width: 768px) {
            .trip-header-top {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                display: none;
            }
            
            .destinations-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <a href="index.php" class="logo">🌍 World Tour</a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="destinations.php">Destinations</a></li>
                <li><a href="posts.php">Travel Stories</a></li>
                <li><a href="my_trips.php">My Trips</a></li>
                <li><a href="gifts.php">Gifts</a></li>
                <li><a href="create_post.php">Share Story</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <a href="my_trips.php" class="back-link">
            <span>←</span> Back to My Trips
        </a>
        
        <!-- Trip Header -->
        <div class="trip-header">
            <div class="trip-header-top">
                <div>
                    <h1 class="trip-title">✈️ <?= htmlspecialchars($trip['title']) ?></h1>
                    <div class="trip-meta">
                        Created by <strong><?= htmlspecialchars($trip['user_name']) ?></strong> on <?= formatDate($trip['created_at']) ?>
                    </div>
                </div>
                
                <div class="trip-actions">
                    <?php if (isLoggedIn() && $trip['user_id'] != $_SESSION['user_id']): ?>
                        <a href="send_gift.php?trip_id=<?= $trip['place_id'] ?>&user_id=<?= $trip['user_id'] ?>" 
                           class="btn btn-success">
                            🎁 Send Gift
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($is_owner): ?>
                        <a href="edit_trip.php?id=<?= $trip_id ?>" class="btn btn-primary">
                            ✏️ Edit
                        </a>
                        <a href="my_trips.php?delete=<?= $trip_id ?>" 
                           class="btn btn-danger"
                           onclick="return confirm('Are you sure you want to delete this trip?')">
                            🗑️ Delete
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Trip Info Cards -->
            <div class="trip-info-grid">
                <div class="info-card">
                    <div class="info-icon">💰</div>
                    <div class="info-label">Budget</div>
                    <div class="info-value">
                        <?= $trip['budget'] ? '$' . number_format($trip['budget'], 2) : 'Not set' ?>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-icon">🌍</div>
                    <div class="info-label">Destinations</div>
                    <div class="info-value"><?= $destinations->num_rows ?></div>
                </div>
                <div class="info-card">
                    <div class="info-icon">📅</div>
                    <div class="info-label">Created</div>
                    <div class="info-value"><?= date('M Y', strtotime($trip['created_at'])) ?></div>
                </div>
            </div>
        </div>
        
        <!-- Schedule Section -->
        <?php if (!empty($trip['schedule'])): ?>
            <div class="section">
                <h2 class="section-title">📅 Trip Schedule</h2>
                <div class="schedule-box">
                    <?= htmlspecialchars($trip['schedule']) ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Destinations Section -->
        <div class="section">
            <h2 class="section-title">🗺️ Destinations</h2>
            
            <?php if ($destinations->num_rows > 0): ?>
                <div class="destinations-grid">
                    <?php 
                    $destinations->data_seek(0);
                    while ($dest = $destinations->fetch_assoc()): 
                        // Get places for this country
                        $country_places = $conn->query("SELECT * FROM tourist_place WHERE country_name = '" . $conn->real_escape_string($dest['country_name']) . "'");
                    ?>
                        <div class="destination-card">
                            <h3 class="destination-name"><?= htmlspecialchars($dest['country_name']) ?></h3>
                            <div class="destination-info">
                                📍 <strong><?= htmlspecialchars($dest['place']) ?></strong>
                            </div>
                            <div class="destination-info">
                                🌤️ Best Season: <strong><?= htmlspecialchars($dest['best_season']) ?></strong>
                            </div>
                            <p style="color:#666;margin-top:0.5rem;">
                                <?= htmlspecialchars($dest['description']) ?>
                            </p>
                            
                            <?php if ($country_places->num_rows > 0): ?>
                                <ul class="places-list">
                                    <li style="font-weight:600;color:#333;padding-top:1rem;">
                                        Popular Places:
                                    </li>
                                    <?php while ($place = $country_places->fetch_assoc()): ?>
                                        <li class="place-item">
                                            <span><?= htmlspecialchars($place['place_name']) ?></span>
                                            <span class="badge badge-primary"><?= htmlspecialchars($place['type']) ?></span>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <a href="destination_detail.php?country=<?= urlencode($dest['country_name']) ?>" 
                                style="display:block;text-align:center;margin-top:1rem;color:#667eea;text-decoration:none;font-weight:500;">
                                View Full Details →
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="text-align:center;color:#666;padding:2rem;">
                    No destinations added to this trip yet.
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>